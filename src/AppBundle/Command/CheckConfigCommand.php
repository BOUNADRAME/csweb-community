<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command pour vérifier la configuration performance de CSWeb
 *
 * Affiche toutes les variables de tuning (PHP, MySQL, PostgreSQL, Apache),
 * teste les connexions, vérifie l'espace disque et donne des recommandations.
 *
 * Usage:
 *   php bin/console csweb:check-config
 *   php bin/console csweb:check-config --test-connections
 *   php bin/console csweb:check-config --json
 *
 * @author Bouna DRAME
 */
class CheckConfigCommand extends Command
{
    protected static $defaultName = 'csweb:check-config';

    protected function configure(): void
    {
        $this
            ->setDescription('Vérifier la configuration performance de CSWeb')
            ->setHelp('Affiche les variables PHP, MySQL, PostgreSQL, Apache et donne des recommandations de tuning.')
            ->addOption('test-connections', 't', InputOption::VALUE_NONE, 'Tester les connexions MySQL et PostgreSQL')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Sortie au format JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $config = $this->gatherConfig();

        if ($input->getOption('json')) {
            $output->writeln(json_encode($config, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->title('CSWeb Performance Configuration');

        $this->displayPhpConfig($io, $config['php']);
        $this->displayApacheConfig($io, $config['apache']);
        $this->displayMysqlConfig($io, $config['mysql']);
        $this->displayPostgresConfig($io, $config['postgres']);
        $this->displayDiskUsage($io);

        if ($input->getOption('test-connections')) {
            $this->testConnections($io);
        }

        $this->displayRecommendations($io, $config);

        return Command::SUCCESS;
    }

    private function gatherConfig(): array
    {
        return [
            'php' => [
                'memory_limit' => ini_get('memory_limit') ?: getenv('PHP_MEMORY_LIMIT') ?: '512M',
                'max_execution_time' => ini_get('max_execution_time') ?: getenv('PHP_MAX_EXECUTION_TIME') ?: '300',
                'max_input_time' => ini_get('max_input_time') ?: getenv('PHP_MAX_INPUT_TIME') ?: '300',
                'upload_max_filesize' => ini_get('upload_max_filesize') ?: getenv('PHP_UPLOAD_MAX_FILESIZE') ?: '100M',
                'post_max_size' => ini_get('post_max_size') ?: getenv('PHP_POST_MAX_SIZE') ?: '100M',
                'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime') ?: getenv('PHP_SESSION_GC_MAXLIFETIME') ?: '7200',
                'opcache.memory_consumption' => ini_get('opcache.memory_consumption') ?: getenv('PHP_OPCACHE_MEMORY') ?: '128',
                'opcache.max_accelerated_files' => ini_get('opcache.max_accelerated_files') ?: getenv('PHP_OPCACHE_MAX_FILES') ?: '10000',
                'php_version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
            ],
            'apache' => [
                'max_request_workers' => getenv('APACHE_MAX_REQUEST_WORKERS') ?: '150',
                'server_limit' => getenv('APACHE_SERVER_LIMIT') ?: '150',
                'keep_alive_timeout' => getenv('APACHE_KEEP_ALIVE_TIMEOUT') ?: '5',
                'max_keep_alive_requests' => getenv('APACHE_MAX_KEEP_ALIVE_REQUESTS') ?: '100',
                'timeout' => getenv('APACHE_TIMEOUT') ?: '300',
            ],
            'mysql' => [
                'max_connections' => getenv('MYSQL_MAX_CONNECTIONS') ?: '200',
                'innodb_buffer_pool_size' => getenv('MYSQL_INNODB_BUFFER_POOL_SIZE') ?: '256M',
                'innodb_log_file_size' => getenv('MYSQL_INNODB_LOG_FILE_SIZE') ?: '64M',
                'slow_query_time' => getenv('MYSQL_SLOW_QUERY_TIME') ?: '2',
                'max_allowed_packet' => getenv('MYSQL_MAX_ALLOWED_PACKET') ?: '64M',
                'thread_cache_size' => getenv('MYSQL_THREAD_CACHE_SIZE') ?: '16',
                'table_open_cache' => getenv('MYSQL_TABLE_OPEN_CACHE') ?: '2000',
                'wait_timeout' => getenv('MYSQL_WAIT_TIMEOUT') ?: '28800',
            ],
            'postgres' => [
                'max_connections' => getenv('PG_MAX_CONNECTIONS') ?: '200',
                'shared_buffers' => getenv('PG_SHARED_BUFFERS') ?: '256MB',
                'effective_cache_size' => getenv('PG_EFFECTIVE_CACHE_SIZE') ?: '1GB',
                'work_mem' => getenv('PG_WORK_MEM') ?: '4MB',
                'maintenance_work_mem' => getenv('PG_MAINTENANCE_WORK_MEM') ?: '64MB',
            ],
        ];
    }

    private function displayPhpConfig(SymfonyStyle $io, array $php): void
    {
        $io->section('PHP Configuration');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['PHP Version', $php['php_version']],
                ['SAPI', $php['sapi']],
                ['memory_limit', $php['memory_limit']],
                ['max_execution_time', $php['max_execution_time']],
                ['max_input_time', $php['max_input_time']],
                ['upload_max_filesize', $php['upload_max_filesize']],
                ['post_max_size', $php['post_max_size']],
                ['session.gc_maxlifetime', $php['session.gc_maxlifetime']],
                ['opcache.memory_consumption', $php['opcache.memory_consumption']],
                ['opcache.max_accelerated_files', $php['opcache.max_accelerated_files']],
            ]
        );
    }

    private function displayApacheConfig(SymfonyStyle $io, array $apache): void
    {
        $io->section('Apache MPM Prefork');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['MaxRequestWorkers', $apache['max_request_workers']],
                ['ServerLimit', $apache['server_limit']],
                ['KeepAliveTimeout', $apache['keep_alive_timeout']],
                ['MaxKeepAliveRequests', $apache['max_keep_alive_requests']],
                ['Timeout', $apache['timeout']],
            ]
        );
    }

    private function displayMysqlConfig(SymfonyStyle $io, array $mysql): void
    {
        $io->section('MySQL Metadata');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['max_connections', $mysql['max_connections']],
                ['innodb_buffer_pool_size', $mysql['innodb_buffer_pool_size']],
                ['innodb_log_file_size', $mysql['innodb_log_file_size']],
                ['long_query_time', $mysql['slow_query_time']],
                ['max_allowed_packet', $mysql['max_allowed_packet']],
                ['thread_cache_size', $mysql['thread_cache_size']],
                ['table_open_cache', $mysql['table_open_cache']],
                ['wait_timeout', $mysql['wait_timeout']],
            ]
        );
    }

    private function displayPostgresConfig(SymfonyStyle $io, array $pg): void
    {
        $io->section('PostgreSQL Breakout');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['max_connections', $pg['max_connections']],
                ['shared_buffers', $pg['shared_buffers']],
                ['effective_cache_size', $pg['effective_cache_size']],
                ['work_mem', $pg['work_mem']],
                ['maintenance_work_mem', $pg['maintenance_work_mem']],
            ]
        );
    }

    private function displayDiskUsage(SymfonyStyle $io): void
    {
        $io->section('Disk Usage');

        $paths = [
            'var/' => '/var/www/html/var',
            'files/' => '/var/www/html/files',
            'var/backups/' => '/var/www/html/var/backups',
        ];

        $rows = [];
        foreach ($paths as $label => $path) {
            if (is_dir($path)) {
                $size = $this->getDirectorySize($path);
                $rows[] = [$label, $this->formatBytes($size)];
            } else {
                $rows[] = [$label, '<fg=yellow>N/A (not found)</>'];
            }
        }

        $diskFree = @disk_free_space('/var/www/html');
        $diskTotal = @disk_total_space('/var/www/html');
        if ($diskFree !== false && $diskTotal !== false) {
            $rows[] = ['Disk free', $this->formatBytes($diskFree) . ' / ' . $this->formatBytes($diskTotal)];
        }

        $io->table(['Path', 'Size'], $rows);
    }

    private function testConnections(SymfonyStyle $io): void
    {
        $io->section('Connection Tests');

        // Test MySQL metadata
        $io->write('Testing MySQL metadata connection... ');
        try {
            $configFile = '/var/www/html/src/AppBundle/config.php';
            if (file_exists($configFile)) {
                require_once $configFile;
                $dsn = sprintf('mysql:host=%s;dbname=%s;port=%s', DBHOST, DBNAME, defined('DBPORT') ? DBPORT : '3306');
                $pdo = new \PDO($dsn, DBUSER, DBPASS, [\PDO::ATTR_TIMEOUT => 5]);
                $version = $pdo->query('SELECT VERSION()')->fetchColumn();
                $io->writeln("<fg=green>OK</> (MySQL $version)");
            } else {
                $io->writeln('<fg=yellow>SKIP</> (config.php not found — run /setup first)');
            }
        } catch (\Exception $e) {
            $io->writeln('<fg=red>FAIL</> (' . $e->getMessage() . ')');
        }

        // Test PostgreSQL breakout
        $io->write('Testing PostgreSQL breakout connection... ');
        $pgHost = getenv('POSTGRES_HOST');
        $pgPort = getenv('POSTGRES_PORT') ?: '5432';
        $pgDb = getenv('POSTGRES_DATABASE');
        $pgUser = getenv('POSTGRES_USER');
        $pgPass = getenv('POSTGRES_PASSWORD');

        if ($pgHost && $pgDb && $pgUser) {
            try {
                $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $pgHost, $pgPort, $pgDb);
                $pdo = new \PDO($dsn, $pgUser, $pgPass, [\PDO::ATTR_TIMEOUT => 5]);
                $version = $pdo->query('SELECT version()')->fetchColumn();
                $shortVersion = explode(' ', $version)[1] ?? $version;
                $io->writeln("<fg=green>OK</> (PostgreSQL $shortVersion)");
            } catch (\Exception $e) {
                $io->writeln('<fg=red>FAIL</> (' . $e->getMessage() . ')');
            }
        } else {
            $io->writeln('<fg=yellow>SKIP</> (PostgreSQL not configured)');
        }
    }

    private function displayRecommendations(SymfonyStyle $io, array $config): void
    {
        $io->section('Recommendations');

        $warnings = [];

        // Check Apache MaxRequestWorkers vs MySQL max_connections
        $apacheWorkers = (int) $config['apache']['max_request_workers'];
        $mysqlMaxConn = (int) $config['mysql']['max_connections'];
        if ($apacheWorkers > $mysqlMaxConn) {
            $warnings[] = "Apache MaxRequestWorkers ($apacheWorkers) > MySQL max_connections ($mysqlMaxConn). "
                . "Each PHP worker may need a MySQL connection. Set MYSQL_MAX_CONNECTIONS >= APACHE_MAX_REQUEST_WORKERS.";
        }

        // Check Apache MaxRequestWorkers vs PG max_connections
        $pgMaxConn = (int) $config['postgres']['max_connections'];
        if ($apacheWorkers > $pgMaxConn) {
            $warnings[] = "Apache MaxRequestWorkers ($apacheWorkers) > PostgreSQL max_connections ($pgMaxConn). "
                . "Set PG_MAX_CONNECTIONS >= APACHE_MAX_REQUEST_WORKERS.";
        }

        // Check ServerLimit vs MaxRequestWorkers
        $serverLimit = (int) $config['apache']['server_limit'];
        if ($serverLimit < $apacheWorkers) {
            $warnings[] = "Apache ServerLimit ($serverLimit) < MaxRequestWorkers ($apacheWorkers). "
                . "ServerLimit must be >= MaxRequestWorkers. Set APACHE_SERVER_LIMIT=$apacheWorkers.";
        }

        // Check post_max_size vs upload_max_filesize
        $postMax = $this->parseSize($config['php']['post_max_size']);
        $uploadMax = $this->parseSize($config['php']['upload_max_filesize']);
        if ($postMax < $uploadMax) {
            $warnings[] = "PHP post_max_size ({$config['php']['post_max_size']}) < upload_max_filesize ({$config['php']['upload_max_filesize']}). "
                . "post_max_size should be >= upload_max_filesize.";
        }

        // Check memory_limit
        $memLimit = $this->parseSize($config['php']['memory_limit']);
        if ($memLimit < 256 * 1024 * 1024) {
            $warnings[] = "PHP memory_limit ({$config['php']['memory_limit']}) is below 256M. "
                . "CSWeb recommends at least 256M for stable operation.";
        }

        if (empty($warnings)) {
            $io->success('Configuration looks good! No issues detected.');
        } else {
            foreach ($warnings as $warning) {
                $io->warning($warning);
            }
        }
    }

    private function getDirectorySize(string $path): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }

    private function parseSize(string $size): int
    {
        $size = trim($size);
        $unit = strtoupper(substr($size, -1));
        $value = (int) $size;
        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }
}
