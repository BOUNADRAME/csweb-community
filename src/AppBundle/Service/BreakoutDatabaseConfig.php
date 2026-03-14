<?php

namespace AppBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service de configuration des bases de données de breakout
 *
 * Permet de gérer plusieurs types de bases de données (PostgreSQL, MySQL, SQL Server)
 * pour le breakout sélectif par dictionnaire.
 *
 * @author Bouna DRAME
 */
class BreakoutDatabaseConfig
{
    // Types de drivers supportés
    public const DRIVER_POSTGRESQL = 'pdo_pgsql';
    public const DRIVER_MYSQL = 'pdo_mysql';
    public const DRIVER_SQLSERVER = 'pdo_sqlsrv';

    // Mapping des types lisibles vers drivers PDO
    private const DRIVER_MAP = [
        'postgresql' => self::DRIVER_POSTGRESQL,
        'postgres' => self::DRIVER_POSTGRESQL,
        'pgsql' => self::DRIVER_POSTGRESQL,
        'mysql' => self::DRIVER_MYSQL,
        'sqlserver' => self::DRIVER_SQLSERVER,
        'mssql' => self::DRIVER_SQLSERVER,
    ];

    private $config;
    private $dictionaryDatabaseMap;

    public function __construct(
        private ParameterBagInterface $params,
        private LoggerInterface $logger
    ) {
        $this->config = [];
        $this->dictionaryDatabaseMap = [];
        $this->loadConfiguration();
    }

    /**
     * Charge la configuration depuis les paramètres Symfony et variables d'environnement
     */
    private function loadConfiguration(): void
    {
        // Configuration PostgreSQL (par défaut)
        $this->config['postgresql'] = [
            'driver' => self::DRIVER_POSTGRESQL,
            'host' => $_ENV['POSTGRES_HOST'] ?? 'localhost',
            'port' => $_ENV['POSTGRES_PORT'] ?? 5432,
            'dbname' => $_ENV['POSTGRES_DATABASE'] ?? 'csweb_analytics',
            'user' => $_ENV['POSTGRES_USER'] ?? 'csweb_analytics',
            'password' => $_ENV['POSTGRES_PASSWORD'] ?? '',
            'charset' => 'utf8',
        ];

        // Configuration MySQL
        $this->config['mysql'] = [
            'driver' => self::DRIVER_MYSQL,
            'host' => $_ENV['MYSQL_HOST'] ?? 'localhost',
            'port' => $_ENV['MYSQL_PORT'] ?? 3306,
            'dbname' => $_ENV['MYSQL_DATABASE'] ?? 'csweb_metadata',
            'user' => $_ENV['MYSQL_USER'] ?? 'csweb',
            'password' => $_ENV['MYSQL_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
        ];

        // Configuration SQL Server (optionnelle)
        if (!empty($_ENV['SQLSERVER_HOST'])) {
            $this->config['sqlserver'] = [
                'driver' => self::DRIVER_SQLSERVER,
                'host' => $_ENV['SQLSERVER_HOST'],
                'port' => $_ENV['SQLSERVER_PORT'] ?? 1433,
                'dbname' => $_ENV['SQLSERVER_DATABASE'] ?? 'csweb_analytics',
                'user' => $_ENV['SQLSERVER_USER'] ?? 'sa',
                'password' => $_ENV['SQLSERVER_PASSWORD'] ?? '',
            ];
        }

        $this->logger->info('BreakoutDatabaseConfig loaded', [
            'available_databases' => array_keys($this->config),
        ]);
    }

    /**
     * Retourne le type de base de données par défaut
     */
    public function getDefaultDatabaseType(): string
    {
        return $_ENV['DEFAULT_BREAKOUT_DB_TYPE'] ?? 'postgresql';
    }

    /**
     * Retourne la configuration de connexion pour un type de base de données
     *
     * @param string $databaseType Type de base (postgresql|mysql|sqlserver)
     * @return array Configuration de connexion Doctrine DBAL
     * @throws \InvalidArgumentException Si le type n'est pas supporté
     */
    public function getDatabaseConfig(string $databaseType): array
    {
        $normalizedType = strtolower($databaseType);

        if (!isset($this->config[$normalizedType])) {
            throw new \InvalidArgumentException(
                "Database type '$databaseType' is not configured. Available: " .
                implode(', ', array_keys($this->config))
            );
        }

        return $this->config[$normalizedType];
    }

    /**
     * Retourne la configuration de connexion pour un dictionnaire spécifique
     *
     * @param string $dictionaryName Nom du dictionnaire
     * @param string|null $customDbType Type de DB personnalisé (override)
     * @return array Configuration de connexion Doctrine DBAL
     */
    public function getDatabaseConfigForDictionary(string $dictionaryName, ?string $customDbType = null): array
    {
        // Si un type custom est fourni, l'utiliser
        if ($customDbType !== null) {
            $dbType = $customDbType;
        }
        // Sinon, vérifier s'il y a une config spécifique pour ce dictionnaire
        elseif (isset($this->dictionaryDatabaseMap[$dictionaryName])) {
            $dbType = $this->dictionaryDatabaseMap[$dictionaryName];
        }
        // Sinon, utiliser le type par défaut
        else {
            $dbType = $this->getDefaultDatabaseType();
        }

        $config = $this->getDatabaseConfig($dbType);

        $this->logger->debug("Database config for dictionary", [
            'dictionary' => $dictionaryName,
            'database_type' => $dbType,
            'driver' => $config['driver'],
            'host' => $config['host'],
            'dbname' => $config['dbname'],
        ]);

        return $config;
    }

    /**
     * Associe un dictionnaire à un type de base de données spécifique
     *
     * @param string $dictionaryName Nom du dictionnaire
     * @param string $databaseType Type de base de données
     */
    public function setDictionaryDatabase(string $dictionaryName, string $databaseType): void
    {
        // Valider que le type existe
        $this->getDatabaseConfig($databaseType);

        $this->dictionaryDatabaseMap[$dictionaryName] = strtolower($databaseType);

        $this->logger->info("Dictionary database mapping set", [
            'dictionary' => $dictionaryName,
            'database_type' => $databaseType,
        ]);
    }

    /**
     * Retourne le driver PDO pour un type de base de données
     *
     * @param string $databaseType Type lisible (postgresql, mysql, sqlserver)
     * @return string Driver PDO (pdo_pgsql, pdo_mysql, pdo_sqlsrv)
     */
    public function getDriverForType(string $databaseType): string
    {
        $normalizedType = strtolower($databaseType);

        if (isset(self::DRIVER_MAP[$normalizedType])) {
            return self::DRIVER_MAP[$normalizedType];
        }

        throw new \InvalidArgumentException("Unknown database type: $databaseType");
    }

    /**
     * Vérifie si un type de base de données est disponible
     *
     * @param string $databaseType Type de base de données
     * @return bool True si configuré
     */
    public function isDatabaseTypeAvailable(string $databaseType): bool
    {
        $normalizedType = strtolower($databaseType);
        return isset($this->config[$normalizedType]);
    }

    /**
     * Retourne tous les types de bases de données disponibles
     *
     * @return array Liste des types disponibles
     */
    public function getAvailableDatabaseTypes(): array
    {
        return array_keys($this->config);
    }

    /**
     * Génère les paramètres de connexion Doctrine DBAL
     * avec création automatique de la base si nécessaire
     *
     * @param string $dictionaryName Nom du dictionnaire
     * @param string|null $customDbType Type de DB (optionnel)
     * @return array Paramètres Doctrine DBAL
     */
    public function generateConnectionParams(string $dictionaryName, ?string $customDbType = null): array
    {
        $config = $this->getDatabaseConfigForDictionary($dictionaryName, $customDbType);

        $params = [
            'driver' => $config['driver'],
            'host' => $config['host'],
            'port' => $config['port'],
            'dbname' => $config['dbname'],
            'user' => $config['user'],
            'password' => $config['password'],
        ];

        // Ajouter charset pour MySQL et PostgreSQL
        if (isset($config['charset'])) {
            $params['charset'] = $config['charset'];
        }

        return $params;
    }

    /**
     * Retourne le nom de schéma pour un dictionnaire
     * (basé sur le label du dictionnaire)
     *
     * @param string $dictionaryName Nom du dictionnaire (ex: KAIROS_DICT)
     * @return string Nom de schéma (ex: kairos)
     */
    public function getSchemaNameForDictionary(string $dictionaryName): string
    {
        // Extraire le label du dictionnaire
        $label = str_replace(" ", "_", str_replace("_DICT", "", $dictionaryName));
        return strtolower($label);
    }

    /**
     * Retourne le préfixe de table pour un dictionnaire
     *
     * @param string $dictionaryName Nom du dictionnaire
     * @return string Préfixe (ex: kairos_)
     */
    public function getTablePrefixForDictionary(string $dictionaryName): string
    {
        return $this->getSchemaNameForDictionary($dictionaryName) . '_';
    }

    /**
     * Construit le nom complet d'une table pour un dictionnaire
     *
     * @param string $dictionaryName Nom du dictionnaire
     * @param string $tableSuffix Suffixe de table (ex: cases, level_1, record_001)
     * @return string Nom complet de la table (ex: kairos_cases)
     */
    public function getFullTableName(string $dictionaryName, string $tableSuffix): string
    {
        return $this->getTablePrefixForDictionary($dictionaryName) . $tableSuffix;
    }

    /**
     * Retourne les informations de configuration pour logs/debug
     *
     * @return array Configuration (passwords masqués)
     */
    public function getConfigSummary(): array
    {
        $summary = [];

        foreach ($this->config as $type => $config) {
            $summary[$type] = [
                'driver' => $config['driver'],
                'host' => $config['host'],
                'port' => $config['port'],
                'dbname' => $config['dbname'],
                'user' => $config['user'],
                'password' => '***MASKED***',
            ];
        }

        return [
            'default_type' => $this->getDefaultDatabaseType(),
            'available_databases' => $summary,
            'dictionary_mappings' => $this->dictionaryDatabaseMap,
        ];
    }
}
