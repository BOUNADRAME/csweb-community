<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use AppBundle\Service\PdoHelper;
use AppBundle\CSPro\Data\BackupScheduler;
use Psr\Log\LoggerInterface;

class BackupCleanupCommand extends Command {

    protected static $defaultName = 'csweb:backup-cleanup';

    public function __construct(private PdoHelper $pdo, private KernelInterface $kernel, private LoggerInterface $logger) {
        parent::__construct();
    }

    protected function configure() {
        $this->setDescription('Purge old MySQL backup files')
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Override retention days (default: from config)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        $scheduler = new BackupScheduler($this->pdo, $this->logger);
        $config = $scheduler->getConfig();

        $days = $input->getOption('days');
        if ($days !== null) {
            $retentionDays = (int) $days;
        } elseif ($config) {
            $retentionDays = (int) $config['retention_days'];
        } else {
            $retentionDays = 30;
        }

        $backupDir = $this->kernel->getProjectDir() . '/var/backups';

        if (!is_dir($backupDir)) {
            $io->text('No backup directory found.');
            return Command::SUCCESS;
        }

        $cutoff = time() - ($retentionDays * 86400);
        $files = glob($backupDir . '/*.sql.gz');
        $deleted = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                $filename = basename($file);
                if (unlink($file)) {
                    $io->text("Deleted: $filename");
                    $deleted++;
                } else {
                    $io->warning("Failed to delete: $filename");
                }
            }
        }

        if ($deleted === 0) {
            $io->success("No backup files older than $retentionDays days.");
        } else {
            $io->success("Purged $deleted backup file(s) older than $retentionDays days.");
        }

        return Command::SUCCESS;
    }
}
