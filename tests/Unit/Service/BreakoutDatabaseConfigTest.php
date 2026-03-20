<?php

namespace Tests\Unit\Service;

use AppBundle\Service\BreakoutDatabaseConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class BreakoutDatabaseConfigTest extends TestCase
{
    private array $savedEnv = [];

    private static array $envKeys = [
        'DEFAULT_BREAKOUT_DB_TYPE',
        'POSTGRES_HOST', 'POSTGRES_PORT', 'POSTGRES_DATABASE', 'POSTGRES_USER', 'POSTGRES_PASSWORD',
        'MYSQL_HOST', 'MYSQL_PORT', 'MYSQL_DATABASE', 'MYSQL_USER', 'MYSQL_PASSWORD',
        'SQLSERVER_HOST', 'SQLSERVER_PORT', 'SQLSERVER_DATABASE', 'SQLSERVER_USER', 'SQLSERVER_PASSWORD',
    ];

    protected function setUp(): void
    {
        foreach (self::$envKeys as $key) {
            $this->savedEnv[$key] = $_ENV[$key] ?? null;
            unset($_ENV[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::$envKeys as $key) {
            if ($this->savedEnv[$key] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $this->savedEnv[$key];
            }
        }
    }

    private function createConfig(): BreakoutDatabaseConfig
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        return new BreakoutDatabaseConfig($params, $logger);
    }

    public function testDefaultDatabaseTypeIsPostgresql(): void
    {
        $config = $this->createConfig();
        $this->assertSame('postgresql', $config->getDefaultDatabaseType());
    }

    public function testDefaultDatabaseTypeReadsEnv(): void
    {
        $_ENV['DEFAULT_BREAKOUT_DB_TYPE'] = 'mysql';
        $config = $this->createConfig();
        $this->assertSame('mysql', $config->getDefaultDatabaseType());
    }

    public function testGetDatabaseConfigPostgresql(): void
    {
        $config = $this->createConfig();
        $pgConfig = $config->getDatabaseConfig('postgresql');
        $this->assertSame('pdo_pgsql', $pgConfig['driver']);
        $this->assertSame('localhost', $pgConfig['host']);
        $this->assertSame(5432, $pgConfig['port']);
    }

    public function testGetDatabaseConfigMysql(): void
    {
        $config = $this->createConfig();
        $myConfig = $config->getDatabaseConfig('mysql');
        $this->assertSame('pdo_mysql', $myConfig['driver']);
        $this->assertSame('localhost', $myConfig['host']);
        $this->assertSame(3306, $myConfig['port']);
    }

    public function testGetDatabaseConfigInvalidTypeThrows(): void
    {
        $config = $this->createConfig();
        $this->expectException(\InvalidArgumentException::class);
        $config->getDatabaseConfig('oracle');
    }

    public function testGetDatabaseConfigReadsEnvVars(): void
    {
        $_ENV['POSTGRES_HOST'] = 'dbserver.local';
        $_ENV['POSTGRES_PORT'] = '5433';
        $_ENV['POSTGRES_DATABASE'] = 'testdb';
        $_ENV['POSTGRES_USER'] = 'testuser';
        $_ENV['POSTGRES_PASSWORD'] = 'secret';

        $config = $this->createConfig();
        $pgConfig = $config->getDatabaseConfig('postgresql');
        $this->assertSame('dbserver.local', $pgConfig['host']);
        $this->assertSame('5433', $pgConfig['port']);
        $this->assertSame('testdb', $pgConfig['dbname']);
        $this->assertSame('testuser', $pgConfig['user']);
        $this->assertSame('secret', $pgConfig['password']);
    }

    public function testSqlserverOnlyAvailableWhenEnvSet(): void
    {
        // No SQLSERVER_HOST set
        $config = $this->createConfig();
        $this->assertFalse($config->isDatabaseTypeAvailable('sqlserver'));

        // Set SQLSERVER_HOST
        $_ENV['SQLSERVER_HOST'] = 'mssql.local';
        $configWithSql = $this->createConfig();
        $this->assertTrue($configWithSql->isDatabaseTypeAvailable('sqlserver'));
    }

    public function testGetDriverForType(): void
    {
        $config = $this->createConfig();
        $this->assertSame('pdo_pgsql', $config->getDriverForType('postgresql'));
        $this->assertSame('pdo_mysql', $config->getDriverForType('mysql'));
        $this->assertSame('pdo_sqlsrv', $config->getDriverForType('sqlserver'));
    }

    public function testGetDriverForTypeAliases(): void
    {
        $config = $this->createConfig();
        $this->assertSame('pdo_pgsql', $config->getDriverForType('postgres'));
        $this->assertSame('pdo_pgsql', $config->getDriverForType('pgsql'));
        $this->assertSame('pdo_sqlsrv', $config->getDriverForType('mssql'));
    }

    public function testGetDriverForTypeUnknownThrows(): void
    {
        $config = $this->createConfig();
        $this->expectException(\InvalidArgumentException::class);
        $config->getDriverForType('oracle');
    }

    public function testIsDatabaseTypeAvailable(): void
    {
        $config = $this->createConfig();
        $this->assertTrue($config->isDatabaseTypeAvailable('postgresql'));
        $this->assertTrue($config->isDatabaseTypeAvailable('mysql'));
    }

    public function testGetAvailableDatabaseTypes(): void
    {
        $config = $this->createConfig();
        $types = $config->getAvailableDatabaseTypes();
        $this->assertContains('postgresql', $types);
        $this->assertContains('mysql', $types);
    }

    public function testGetSchemaNameForDictionary(): void
    {
        $config = $this->createConfig();
        $this->assertSame('kairos', $config->getSchemaNameForDictionary('KAIROS_DICT'));
    }

    public function testGetTablePrefixForDictionary(): void
    {
        $config = $this->createConfig();
        $this->assertSame('kairos_', $config->getTablePrefixForDictionary('KAIROS_DICT'));
    }

    public function testGetFullTableName(): void
    {
        $config = $this->createConfig();
        $this->assertSame('kairos_cases', $config->getFullTableName('KAIROS_DICT', 'cases'));
    }

    public function testSetDictionaryDatabase(): void
    {
        $config = $this->createConfig();
        // Should not throw for valid type
        $config->setDictionaryDatabase('MY_DICT', 'mysql');
        $dbConfig = $config->getDatabaseConfigForDictionary('MY_DICT');
        $this->assertSame('pdo_mysql', $dbConfig['driver']);
    }

    public function testSetDictionaryDatabaseInvalidTypeThrows(): void
    {
        $config = $this->createConfig();
        $this->expectException(\InvalidArgumentException::class);
        $config->setDictionaryDatabase('MY_DICT', 'oracle');
    }

    public function testGetDatabaseConfigForDictionaryFallsBackToDefault(): void
    {
        $config = $this->createConfig();
        // No mapping set — should fall back to default (postgresql)
        $dbConfig = $config->getDatabaseConfigForDictionary('UNKNOWN_DICT');
        $this->assertSame('pdo_pgsql', $dbConfig['driver']);
    }

    public function testGetDatabaseConfigForDictionaryCustomOverride(): void
    {
        $config = $this->createConfig();
        $dbConfig = $config->getDatabaseConfigForDictionary('ANY_DICT', 'mysql');
        $this->assertSame('pdo_mysql', $dbConfig['driver']);
    }

    public function testGenerateConnectionParams(): void
    {
        $config = $this->createConfig();
        $params = $config->generateConnectionParams('KAIROS_DICT');
        $this->assertArrayHasKey('driver', $params);
        $this->assertArrayHasKey('host', $params);
        $this->assertArrayHasKey('port', $params);
        $this->assertArrayHasKey('dbname', $params);
        $this->assertArrayHasKey('user', $params);
        $this->assertArrayHasKey('password', $params);
    }

    public function testGetConfigSummaryMasksPasswords(): void
    {
        $config = $this->createConfig();
        $summary = $config->getConfigSummary();
        $this->assertArrayHasKey('default_type', $summary);
        $this->assertArrayHasKey('available_databases', $summary);
        $this->assertArrayHasKey('dictionary_mappings', $summary);

        foreach ($summary['available_databases'] as $type => $dbConfig) {
            $this->assertSame('***MASKED***', $dbConfig['password'], "Password not masked for $type");
        }
    }
}
