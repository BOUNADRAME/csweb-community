<?php

namespace AppBundle\Service;

use Psr\Log\LoggerInterface;

/**
 * Service de détection des drivers de base de données PHP
 *
 * Vérifie les modules PHP installés et leur compatibilité avec les bases de données
 * supportées (PostgreSQL, MySQL, SQL Server).
 *
 * @author Bouna DRAME
 */
class DatabaseDriverDetector
{
    // Extensions PHP requises par type de base de données
    private const REQUIRED_EXTENSIONS = [
        'postgresql' => ['pdo', 'pdo_pgsql', 'pgsql'],
        'mysql' => ['pdo', 'pdo_mysql', 'mysqli'],
        'sqlserver' => ['pdo', 'pdo_sqlsrv', 'sqlsrv'],
    ];

    // Extensions PHP optionnelles mais recommandées
    private const RECOMMENDED_EXTENSIONS = [
        'mbstring',
        'xml',
        'intl',
        'json',
        'openssl',
        'zip',
    ];

    private $detectionResults;

    public function __construct(private LoggerInterface $logger)
    {
        $this->detectionResults = [];
        $this->detectDrivers();
    }

    /**
     * Détecte tous les drivers disponibles
     */
    private function detectDrivers(): void
    {
        $loadedExtensions = array_map('strtolower', get_loaded_extensions());

        foreach (self::REQUIRED_EXTENSIONS as $dbType => $requiredExts) {
            $this->detectionResults[$dbType] = [
                'available' => true,
                'extensions' => [],
                'missing_extensions' => [],
            ];

            foreach ($requiredExts as $ext) {
                $isLoaded = in_array($ext, $loadedExtensions, true);

                $this->detectionResults[$dbType]['extensions'][$ext] = $isLoaded;

                if (!$isLoaded) {
                    $this->detectionResults[$dbType]['available'] = false;
                    $this->detectionResults[$dbType]['missing_extensions'][] = $ext;
                }
            }
        }

        $this->logger->info('Database drivers detected', [
            'postgresql' => $this->detectionResults['postgresql']['available'],
            'mysql' => $this->detectionResults['mysql']['available'],
            'sqlserver' => $this->detectionResults['sqlserver']['available'],
        ]);
    }

    /**
     * Vérifie si un type de base de données est supporté
     *
     * @param string $databaseType Type de base (postgresql|mysql|sqlserver)
     * @return bool True si tous les modules requis sont installés
     */
    public function isDatabaseTypeSupported(string $databaseType): bool
    {
        $normalizedType = strtolower($databaseType);

        if (!isset($this->detectionResults[$normalizedType])) {
            return false;
        }

        return $this->detectionResults[$normalizedType]['available'];
    }

    /**
     * Retourne les extensions manquantes pour un type de base de données
     *
     * @param string $databaseType Type de base
     * @return array Extensions manquantes
     */
    public function getMissingExtensions(string $databaseType): array
    {
        $normalizedType = strtolower($databaseType);

        if (!isset($this->detectionResults[$normalizedType])) {
            return [];
        }

        return $this->detectionResults[$normalizedType]['missing_extensions'];
    }

    /**
     * Retourne le statut de toutes les extensions pour un type de DB
     *
     * @param string $databaseType Type de base
     * @return array Status des extensions [extension => bool]
     */
    public function getExtensionStatus(string $databaseType): array
    {
        $normalizedType = strtolower($databaseType);

        if (!isset($this->detectionResults[$normalizedType])) {
            return [];
        }

        return $this->detectionResults[$normalizedType]['extensions'];
    }

    /**
     * Génère un rapport complet de détection
     *
     * @return array Rapport détaillé
     */
    public function generateReport(): array
    {
        $report = [
            'php_version' => PHP_VERSION,
            'databases' => [],
            'recommended_extensions' => [],
            'system_info' => [
                'os' => PHP_OS,
                'sapi' => PHP_SAPI,
                'loaded_extensions_count' => count(get_loaded_extensions()),
            ],
        ];

        // Rapport par type de base de données
        foreach ($this->detectionResults as $dbType => $result) {
            $report['databases'][$dbType] = [
                'supported' => $result['available'],
                'extensions' => $result['extensions'],
                'missing_extensions' => $result['missing_extensions'],
                'status' => $result['available'] ? '✅ Available' : '❌ Missing extensions',
            ];
        }

        // Extensions recommandées
        $loadedExtensions = get_loaded_extensions();
        foreach (self::RECOMMENDED_EXTENSIONS as $ext) {
            $isLoaded = in_array($ext, $loadedExtensions, true);
            $report['recommended_extensions'][$ext] = [
                'loaded' => $isLoaded,
                'status' => $isLoaded ? '✅ Installed' : '⚠️ Not installed (recommended)',
            ];
        }

        return $report;
    }

    /**
     * Affiche un rapport formaté en console
     *
     * @return string Rapport formaté
     */
    public function getFormattedReport(): string
    {
        $report = $this->generateReport();
        $output = [];

        $output[] = "========================================";
        $output[] = "PHP Database Drivers Detection Report";
        $output[] = "========================================";
        $output[] = "";
        $output[] = "PHP Version: " . $report['php_version'];
        $output[] = "OS: " . $report['system_info']['os'];
        $output[] = "SAPI: " . $report['system_info']['sapi'];
        $output[] = "Loaded Extensions: " . $report['system_info']['loaded_extensions_count'];
        $output[] = "";

        $output[] = "Database Drivers:";
        $output[] = "----------------------------------------";
        foreach ($report['databases'] as $dbType => $info) {
            $output[] = strtoupper($dbType) . ": " . $info['status'];

            foreach ($info['extensions'] as $ext => $loaded) {
                $status = $loaded ? '✅' : '❌';
                $output[] = "  $status $ext";
            }

            if (!empty($info['missing_extensions'])) {
                $output[] = "  ⚠️ Missing: " . implode(', ', $info['missing_extensions']);
            }

            $output[] = "";
        }

        $output[] = "Recommended Extensions:";
        $output[] = "----------------------------------------";
        foreach ($report['recommended_extensions'] as $ext => $info) {
            $output[] = $info['status'] . " - $ext";
        }
        $output[] = "";

        $output[] = "========================================";
        $output[] = "Installation Commands (Ubuntu/Debian):";
        $output[] = "========================================";

        // Générer commandes d'installation
        foreach ($report['databases'] as $dbType => $info) {
            if (!empty($info['missing_extensions'])) {
                $packages = array_map(
                    fn($ext) => "php-" . str_replace('pdo_', '', $ext),
                    $info['missing_extensions']
                );
                $output[] = "# For $dbType:";
                $output[] = "sudo apt-get install " . implode(' ', array_unique($packages));
                $output[] = "";
            }
        }

        $missingRecommended = array_filter(
            $report['recommended_extensions'],
            fn($info) => !$info['loaded']
        );

        if (!empty($missingRecommended)) {
            $packages = array_map(
                fn($ext) => "php-$ext",
                array_keys($missingRecommended)
            );
            $output[] = "# Recommended extensions:";
            $output[] = "sudo apt-get install " . implode(' ', $packages);
            $output[] = "";
        }

        $output[] = "========================================";

        return implode("\n", $output);
    }

    /**
     * Vérifie si toutes les extensions recommandées sont installées
     *
     * @return bool True si toutes sont installées
     */
    public function areRecommendedExtensionsInstalled(): bool
    {
        $loadedExtensions = get_loaded_extensions();

        foreach (self::RECOMMENDED_EXTENSIONS as $ext) {
            if (!in_array($ext, $loadedExtensions, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retourne la liste de tous les drivers PDO disponibles
     *
     * @return array Liste des drivers PDO
     */
    public function getAvailablePdoDrivers(): array
    {
        if (!extension_loaded('pdo')) {
            return [];
        }

        return \PDO::getAvailableDrivers();
    }

    /**
     * Vérifie si un driver PDO spécifique est disponible
     *
     * @param string $driver Nom du driver (ex: pgsql, mysql, sqlsrv)
     * @return bool True si disponible
     */
    public function isPdoDriverAvailable(string $driver): bool
    {
        return in_array($driver, $this->getAvailablePdoDrivers(), true);
    }

    /**
     * Génère des instructions d'installation pour un OS spécifique
     *
     * @param string $os Type d'OS (ubuntu|debian|centos|rhel|alpine|macos)
     * @param string $databaseType Type de base de données
     * @return array Instructions d'installation
     */
    public function getInstallationInstructions(string $os, string $databaseType): array
    {
        $normalizedType = strtolower($databaseType);
        $missingExts = $this->getMissingExtensions($normalizedType);

        if (empty($missingExts)) {
            return [
                'message' => "All required extensions for $databaseType are already installed.",
                'commands' => [],
            ];
        }

        $instructions = [
            'message' => "Missing extensions for $databaseType: " . implode(', ', $missingExts),
            'commands' => [],
        ];

        switch (strtolower($os)) {
            case 'ubuntu':
            case 'debian':
                $packages = array_map(
                    fn($ext) => "php-" . str_replace('pdo_', '', $ext),
                    $missingExts
                );
                $instructions['commands'] = [
                    'sudo apt-get update',
                    'sudo apt-get install -y ' . implode(' ', array_unique($packages)),
                    'sudo systemctl restart apache2',  // ou php-fpm
                ];
                break;

            case 'centos':
            case 'rhel':
                $packages = array_map(
                    fn($ext) => "php-" . str_replace('pdo_', '', $ext),
                    $missingExts
                );
                $instructions['commands'] = [
                    'sudo yum install -y ' . implode(' ', array_unique($packages)),
                    'sudo systemctl restart httpd',
                ];
                break;

            case 'alpine':
                $packages = array_map(
                    fn($ext) => "php-" . str_replace('pdo_', '', $ext),
                    $missingExts
                );
                $instructions['commands'] = [
                    'apk add ' . implode(' ', array_unique($packages)),
                ];
                break;

            case 'macos':
                $instructions['commands'] = [
                    'brew install php',
                    '# Or install specific extensions via PECL',
                ];
                break;
        }

        return $instructions;
    }

    /**
     * Teste la connexion à une base de données avec les paramètres fournis
     *
     * @param array $connectionParams Paramètres de connexion Doctrine DBAL
     * @return array Résultat du test [success => bool, message => string]
     */
    public function testConnection(array $connectionParams): array
    {
        try {
            // Extraire le type de driver
            $driver = $connectionParams['driver'] ?? '';

            // Vérifier que l'extension est chargée
            $driverName = str_replace('pdo_', '', $driver);

            if (!$this->isPdoDriverAvailable($driverName)) {
                return [
                    'success' => false,
                    'message' => "PDO driver '$driverName' is not available. Install the required PHP extension.",
                ];
            }

            // Tenter une connexion PDO
            $dsn = $this->buildDsn($connectionParams);
            $pdo = new \PDO(
                $dsn,
                $connectionParams['user'],
                $connectionParams['password']
            );

            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Test de requête simple
            $pdo->query('SELECT 1');

            return [
                'success' => true,
                'message' => "Connection successful to {$connectionParams['host']}:{$connectionParams['port']}/{$connectionParams['dbname']}",
            ];

        } catch (\PDOException $e) {
            return [
                'success' => false,
                'message' => "Connection failed: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Construit le DSN pour PDO
     *
     * @param array $params Paramètres de connexion
     * @return string DSN
     */
    private function buildDsn(array $params): string
    {
        $driver = str_replace('pdo_', '', $params['driver']);

        switch ($driver) {
            case 'pgsql':
                return sprintf(
                    "pgsql:host=%s;port=%d;dbname=%s",
                    $params['host'],
                    $params['port'],
                    $params['dbname']
                );

            case 'mysql':
                return sprintf(
                    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                    $params['host'],
                    $params['port'],
                    $params['dbname'],
                    $params['charset'] ?? 'utf8mb4'
                );

            case 'sqlsrv':
                return sprintf(
                    "sqlsrv:Server=%s,%d;Database=%s",
                    $params['host'],
                    $params['port'],
                    $params['dbname']
                );

            default:
                throw new \InvalidArgumentException("Unknown driver: {$params['driver']}");
        }
    }
}
