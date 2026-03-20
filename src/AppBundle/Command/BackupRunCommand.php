<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use AppBundle\Service\PdoHelper;
use AppBundle\CSPro\Data\BackupScheduler;
use Psr\Log\LoggerInterface;

class BackupRunCommand extends Command {

    protected static $defaultName = 'csweb:backup-run';
    use LockableTrait;

    public function __construct(private PdoHelper $pdo, private KernelInterface $kernel, private LoggerInterface $logger) {
        parent::__construct();
    }

    protected function configure() {
        $this->setDescription('Run due MySQL backup (designed to be called every minute via crontab)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        if (!$this->lock()) {
            $io->warning('A backup is already running in another process.');
            return Command::SUCCESS;
        }

        $scheduler = new BackupScheduler($this->pdo, $this->logger);
        $dueBackup = $scheduler->getDueBackup();

        if (!$dueBackup) {
            $io->text('No backup due.');
            $this->release();
            return Command::SUCCESS;
        }

        $projectDir = $this->kernel->getProjectDir();
        $backupDir = $projectDir . '/var/backups';
        $logDir = $projectDir . '/var/logs/backup';

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Load DB credentials
        $configPath = $projectDir . '/src/AppBundle/config.php';
        if (!file_exists($configPath)) {
            $io->error('Config file not found: ' . $configPath);
            $this->release();
            return Command::FAILURE;
        }
        require_once $configPath;

        $timestamp = date('Y-m-d_H-i-s');
        $backupFileName = 'csweb_metadata_' . $timestamp . '.sql.gz';
        $backupFilePath = $backupDir . '/' . $backupFileName;
        $logFileName = 'backup_' . $timestamp . '.log';
        $logFilePath = $logDir . '/' . $logFileName;

        $io->text('Starting MySQL backup...');
        $this->logger->info('Backup: starting MySQL dump');

        // Register file in DB as in_progress
        $scheduler->registerFile($backupFileName, 'scheduled', $logFileName);

        // Build mysqldump command piped to gzip
        $cmd = sprintf(
            'mysqldump --skip-ssl -h %s -u %s --password=%s %s | gzip > %s',
            escapeshellarg(DBHOST),
            escapeshellarg(DBUSER),
            escapeshellarg(DBPASS),
            escapeshellarg(DBNAME),
            escapeshellarg($backupFilePath)
        );

        $process = Process::fromShellCommandLine($cmd);
        $process->setTimeout(3600);
        $process->run();

        $exitCode = $process->getExitCode();

        // Write log
        $logContent = "Backup Command\n"
            . "Started: $timestamp\n"
            . "Database: " . DBNAME . "@" . DBHOST . "\n"
            . "Output file: $backupFileName\n"
            . "Exit code: $exitCode\n\n"
            . "--- STDOUT ---\n" . $process->getOutput() . "\n"
            . "--- STDERR ---\n" . $process->getErrorOutput() . "\n";

        file_put_contents($logFilePath, $logContent);

        $scheduler->markRun($exitCode, $logFileName);

        // Update file record with result
        $fileSize = file_exists($backupFilePath) ? (int) filesize($backupFilePath) : 0;
        $scheduler->completeFile($backupFileName, $exitCode, $fileSize);

        if ($exitCode === 0) {
            $size = filesize($backupFilePath);
            $io->success("Backup completed: $backupFileName (" . $this->formatBytes($size) . ")");

            // Purge old backups
            $retentionDays = (int) $dueBackup['retention_days'];
            $this->purgeOldBackups($backupDir, $retentionDays, $io, $scheduler);
        } else {
            $io->error("Backup failed (exit code: $exitCode). See log: $logFileName");
        }

        $this->release();
        return Command::SUCCESS;
    }

    private function purgeOldBackups(string $backupDir, int $retentionDays, SymfonyStyle $io, BackupScheduler $scheduler): void {
        $cutoff = time() - ($retentionDays * 86400);
        $files = glob($backupDir . '/*.sql.gz');
        $deleted = 0;
        $deletedNames = [];

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                $fname = basename($file);
                if (unlink($file)) {
                    $deleted++;
                    $deletedNames[] = $fname;
                }
            }
        }

        // Delete associated logs + DB records for purged files
        if (!empty($deletedNames)) {
            $logDir = dirname($backupDir) . '/logs/backup';
            foreach ($deletedNames as $dName) {
                $logName = preg_replace('/^csweb_metadata_/', 'backup_', preg_replace('/\.sql\.gz$/', '', $dName)) . '.log';
                $logPath = $logDir . '/' . $logName;
                if (file_exists($logPath)) {
                    unlink($logPath);
                }
            }
            $scheduler->deleteFileRecords($deletedNames);
        }

        if ($deleted > 0) {
            $io->text("Purged $deleted old backup(s) older than $retentionDays days.");
        }
    }

    private function formatBytes(int $bytes): string {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
