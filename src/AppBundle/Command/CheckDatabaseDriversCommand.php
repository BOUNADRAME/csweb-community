<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use AppBundle\Service\DatabaseDriverDetector;
use AppBundle\Service\BreakoutDatabaseConfig;

/**
 * Console command pour vérifier les drivers de base de données disponibles
 *
 * Usage:
 *   php bin/console csweb:check-database-drivers
 *   php bin/console csweb:check-database-drivers --test-connections
 *
 * @author Bouna DRAME
 */
class CheckDatabaseDriversCommand extends Command
{
    protected static $defaultName = 'csweb:check-database-drivers';

    public function __construct(
        private DatabaseDriverDetector $driverDetector,
        private BreakoutDatabaseConfig $dbConfig
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Check available database drivers and PHP extensions')
            ->setHelp('This command checks which database drivers (PostgreSQL, MySQL, SQL Server) are available and can be used for breakout.')
            ->addOption(
                'test-connections',
                't',
                InputOption::VALUE_NONE,
                'Test actual connections to configured databases'
            )
            ->addOption(
                'json',
                'j',
                InputOption::VALUE_NONE,
                'Output in JSON format'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Mode JSON
        if ($input->getOption('json')) {
            return $this->executeJsonOutput($output);
        }

        // Mode formaté
        $io->title('CSWeb Database Drivers Check');

        // 1. Afficher le rapport de détection
        $report = $this->driverDetector->generateReport();

        $this->displaySystemInfo($io, $report);
        $this->displayDatabaseDrivers($io, $report);
        $this->displayRecommendedExtensions($io, $report);

        // 2. Afficher la configuration actuelle
        $this->displayCurrentConfiguration($io);

        // 3. Tester les connexions si demandé
        if ($input->getOption('test-connections')) {
            $this->testConnections($io);
        }

        // 4. Afficher les instructions d'installation si nécessaire
        $this->displayInstallationInstructions($io, $report);

        // 5. Résumé final
        $this->displaySummary($io, $report);

        return Command::SUCCESS;
    }

    private function displaySystemInfo(SymfonyStyle $io, array $report): void
    {
        $io->section('System Information');

        $io->table(
            ['Property', 'Value'],
            [
                ['PHP Version', $report['php_version']],
                ['Operating System', $report['system_info']['os']],
                ['SAPI', $report['system_info']['sapi']],
                ['Loaded Extensions', $report['system_info']['loaded_extensions_count']],
            ]
        );
    }

    private function displayDatabaseDrivers(SymfonyStyle $io, array $report): void
    {
        $io->section('Database Drivers');

        $rows = [];
        foreach ($report['databases'] as $dbType => $info) {
            $status = $info['supported'] ? '<fg=green>✅ Available</>' : '<fg=red>❌ Missing</>';

            $extensions = [];
            foreach ($info['extensions'] as $ext => $loaded) {
                $icon = $loaded ? '<fg=green>✅</>' : '<fg=red>❌</>';
                $extensions[] = "$icon $ext";
            }

            $rows[] = [
                strtoupper($dbType),
                $status,
                implode("\n", $extensions),
            ];
        }

        $io->table(['Database', 'Status', 'Extensions'], $rows);
    }

    private function displayRecommendedExtensions(SymfonyStyle $io, array $report): void
    {
        $io->section('Recommended Extensions');

        $rows = [];
        foreach ($report['recommended_extensions'] as $ext => $info) {
            $status = $info['loaded'] ? '<fg=green>✅ Installed</>' : '<fg=yellow>⚠️ Missing</>';
            $rows[] = [$ext, $status];
        }

        $io->table(['Extension', 'Status'], $rows);
    }

    private function displayCurrentConfiguration(SymfonyStyle $io): void
    {
        $io->section('Current Breakout Configuration');

        $configSummary = $this->dbConfig->getConfigSummary();

        $io->writeln("<info>Default Database Type:</info> {$configSummary['default_type']}");
        $io->newLine();

        $rows = [];
        foreach ($configSummary['available_databases'] as $type => $config) {
            $rows[] = [
                strtoupper($type),
                $config['driver'],
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['user'],
            ];
        }

        $io->table(
            ['Type', 'Driver', 'Host', 'Port', 'Database', 'User'],
            $rows
        );

        if (!empty($configSummary['dictionary_mappings'])) {
            $io->writeln('<info>Dictionary Mappings:</info>');
            foreach ($configSummary['dictionary_mappings'] as $dict => $dbType) {
                $io->writeln("  • $dict → $dbType");
            }
        }
    }

    private function testConnections(SymfonyStyle $io): void
    {
        $io->section('Connection Tests');

        $availableTypes = $this->dbConfig->getAvailableDatabaseTypes();

        foreach ($availableTypes as $dbType) {
            // Vérifier d'abord que le driver est supporté
            if (!$this->driverDetector->isDatabaseTypeSupported($dbType)) {
                $io->warning("Skipping $dbType: driver not available");
                continue;
            }

            $io->write("Testing connection to <info>$dbType</info>... ");

            try {
                $config = $this->dbConfig->getDatabaseConfig($dbType);
                $result = $this->driverDetector->testConnection($config);

                if ($result['success']) {
                    $io->writeln('<fg=green>✅ SUCCESS</>');
                    $io->writeln("  └─ {$result['message']}");
                } else {
                    $io->writeln('<fg=red>❌ FAILED</>');
                    $io->writeln("  └─ {$result['message']}");
                }
            } catch (\Exception $e) {
                $io->writeln('<fg=red>❌ ERROR</>');
                $io->writeln("  └─ {$e->getMessage()}");
            }

            $io->newLine();
        }
    }

    private function displayInstallationInstructions(SymfonyStyle $io, array $report): void
    {
        $hasProblems = false;

        foreach ($report['databases'] as $dbType => $info) {
            if (!$info['supported']) {
                $hasProblems = true;
                break;
            }
        }

        if (!$hasProblems && $this->driverDetector->areRecommendedExtensionsInstalled()) {
            return; // Tout est bon
        }

        $io->section('Installation Instructions');

        $io->writeln('<comment>The following commands will install missing extensions:</comment>');
        $io->newLine();

        // Détecter l'OS
        $os = PHP_OS_FAMILY;
        $osName = match($os) {
            'Linux' => 'ubuntu',  // Assumer Ubuntu par défaut
            'Darwin' => 'macos',
            default => 'ubuntu',
        };

        // Instructions pour chaque base de données manquante
        foreach ($report['databases'] as $dbType => $info) {
            if (!empty($info['missing_extensions'])) {
                $instructions = $this->driverDetector->getInstallationInstructions($osName, $dbType);

                $io->writeln("<fg=yellow>For $dbType:</>");
                foreach ($instructions['commands'] as $command) {
                    $io->writeln("  <fg=cyan>$command</>");
                }
                $io->newLine();
            }
        }

        $io->note('After installing extensions, restart your web server and PHP-FPM');
    }

    private function displaySummary(SymfonyStyle $io, array $report): void
    {
        $io->section('Summary');

        $supportedDbs = array_filter(
            $report['databases'],
            fn($info) => $info['supported']
        );

        $totalDbs = count($report['databases']);
        $supportedCount = count($supportedDbs);

        if ($supportedCount === $totalDbs) {
            $io->success("All database types are supported ($supportedCount/$totalDbs)");
        } elseif ($supportedCount > 0) {
            $io->warning("Some database types are missing extensions ($supportedCount/$totalDbs supported)");
        } else {
            $io->error("No database types are supported. Please install required PHP extensions.");
        }

        // Recommandations
        if ($supportedCount > 0) {
            $io->writeln('<info>Available for breakout:</info>');
            foreach ($supportedDbs as $dbType => $info) {
                $io->writeln("  ✅ " . strtoupper($dbType));
            }
        }
    }

    private function executeJsonOutput(OutputInterface $output): int
    {
        $report = $this->driverDetector->generateReport();
        $configSummary = $this->dbConfig->getConfigSummary();

        $jsonOutput = [
            'system' => $report['system_info'],
            'php_version' => $report['php_version'],
            'databases' => $report['databases'],
            'recommended_extensions' => $report['recommended_extensions'],
            'configuration' => $configSummary,
            'pdo_drivers' => $this->driverDetector->getAvailablePdoDrivers(),
        ];

        $output->writeln(json_encode($jsonOutput, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }
}
