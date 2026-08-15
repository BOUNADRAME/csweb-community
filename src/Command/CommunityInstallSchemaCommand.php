<?php

namespace App\Command;

use App\CSPro\Community\CommunityPermissions;
use App\CSPro\Community\CommunitySchemaInstaller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Installs the Community layer's schema additions on top of a stock
 * CSWeb 8.1 database.
 *
 * Safe to run repeatedly: every step is idempotent, which is why
 * docker-entrypoint.sh calls it on each boot. It never touches the rows
 * owned by the upstream installer.
 */
class CommunityInstallSchemaCommand extends Command {

    protected static $defaultName = 'csweb:community:install-schema';
    protected static $defaultDescription = 'Install the Community layer permissions on top of the stock CSWeb schema';

    public function __construct(private CommunitySchemaInstaller $installer) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be installed without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        $installed = $this->installer->getInstalledVersion();
        $target = CommunitySchemaInstaller::COMMUNITY_SCHEMA_VERSION;

        if ($input->getOption('dry-run')) {
            $io->text(sprintf('Community schema: installed %d, target %d', $installed, $target));
            if ($installed >= $target) {
                $io->success('Already up to date, nothing to do.');
                return Command::SUCCESS;
            }
            $rows = [];
            foreach (CommunityPermissions::PERMISSIONS as $id => $name) {
                $rows[] = [$id, $name];
            }
            $io->table(['id', 'permission'], $rows);
            return Command::SUCCESS;
        }

        try {
            $result = $this->installer->install();
        } catch (\Exception $e) {
            $io->error('Community schema install failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if ($installed >= $target) {
            $io->text(sprintf('Community schema already at version %d.', $result));
        } else {
            $io->success(sprintf('Community schema installed: version %d -> %d.', $installed, $result));
        }

        return Command::SUCCESS;
    }
}
