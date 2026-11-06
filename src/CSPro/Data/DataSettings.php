<?php

namespace App\CSPro\Data;

use App\CSPro\DictionarySchemaHelper;
use App\Service\PdoHelper;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Schema;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DBALException;

class DataSettings {

    public function __construct(private PdoHelper $pdo, private LoggerInterface $logger)
    {
    }

    public function getDataSettings() {
        $dataSettings = $this->pdo->query('SELECT `cspro_dictionaries`.`id` as id, `name` as name, `dictionary_label` as label,  `host_name` as targetHostName, `cspro_dictionaries_schema`.`port` as targetPort, `cspro_dictionaries_schema`.`db_type` as dbType, `schema_name` as targetSchemaName,'
                        . ' `schema_user_name` as dbUserName, AES_DECRYPT(`schema_password`, \'cspro\') as dbPassword, `additional_config` as additionalConfig, `map_info` as mapInfo FROM `cspro_dictionaries_schema` RIGHT JOIN cspro_dictionaries'
                        . '  ON dictionary_id = cspro_dictionaries.id    ORDER BY dictionary_label')->fetchAll();
        $this->getDataCounts($dataSettings);

        //clear password field
        foreach ($dataSettings as &$dataSetting) {
            $dataSetting['dbPassword'] = "";
        }
        return $dataSettings;
    }

    public function getDataSetting($dictionaryName, $clearPassWord) {
        $bind = [];
        $dataSetting = null;
        try {
            $stm = 'SELECT `cspro_dictionaries`.`id` as id, `name` as name, dictionary_label as label,  `host_name` as targetHostName, `cspro_dictionaries_schema`.`port` as targetPort, `cspro_dictionaries_schema`.`db_type` as dbType, `schema_name` as targetSchemaName,'
                    . ' `schema_user_name` as dbUserName, AES_DECRYPT(`schema_password`, \'cspro\') as dbPassword, `additional_config` as additionalConfig, `map_info` as mapInfo FROM `cspro_dictionaries_schema` RIGHT JOIN cspro_dictionaries'
                    . '  ON dictionary_id = cspro_dictionaries.id  WHERE name = :dictName';

            $bind['dictName'] = $dictionaryName;
            $dataSetting = $this->pdo->fetchOne($stm, $bind);
            //clear password field
            if ($clearPassWord && $dataSetting) {
                $dataSetting['dbPassword'] = "";
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed getting data settings: ' . $dictionaryName, ["context" => (string) $e]);
            throw $e;
        }
        return $dataSetting;
    }

    /**
     * Community layer: map the `db_type` stored per dictionary to a DBAL
     * driver. Upstream only ever talks to MySQL breakout targets, so it
     * hardcodes pdo_mysql; the Community fork supports PostgreSQL, MySQL
     * and SQL Server targets.
     */
    public static function resolveDriver(string $dbType): string {
        return match (strtolower($dbType)) {
            'mysql'     => 'pdo_mysql',
            'sqlserver' => 'pdo_sqlsrv',
            default     => 'pdo_pgsql',   // postgresql
        };
    }

    /**
     * Community layer: build the DBAL connection parameters for a breakout
     * target, honouring the per-dictionary driver and port, and routing
     * through the SSH tunnel when BREAKOUT_CONNECTION_MODE says so.
     */
    private function buildConnectionParams(array $dataSetting): array {
        $driver = self::resolveDriver($dataSetting['dbType'] ?? 'postgresql');
        // Resolve effective host/port via BREAKOUT_CONNECTION_MODE so that
        // both "test connection" (when adding/updating a config) and the
        // actual breakout queries go through the SSH tunnel when configured.
        $resolved = \App\Service\BreakoutConnectionResolver::resolve([
            'host_name' => $dataSetting['targetHostName'] ?? null,
            'port'      => $dataSetting['targetPort'] ?? null,
        ]);
        $params = [
            'dbname'   => $dataSetting['targetSchemaName'],
            'user'     => $dataSetting['dbUserName'],
            'password' => $dataSetting['dbPassword'],
            'host'     => $resolved['host'],
            'driver'   => $driver,
        ];
        // Omit the port entirely when unset so DBAL falls back to the
        // driver's default (5432 / 3306 / 1433) instead of receiving null.
        if ($resolved['port'] !== null) {
            $params['port'] = $resolved['port'];
        }
        return $params;
    }

    /**
     * Community layer: "Test connection" in Add / Edit Configuration.
     * Connects with the exact parameters the breakout will use (driver, port,
     * SSH tunnel), runs a trivial query, and turns the usual failures into a
     * short, actionable message. Nothing is written.
     *
     * @return array{ok:bool, message:string, server?:string}
     */
    public function testConnection(array $dataSetting): array {
        foreach (['targetHostName', 'targetSchemaName', 'dbUserName', 'dbPassword'] as $field) {
            if (trim((string) ($dataSetting[$field] ?? '')) === '') {
                return ['ok' => false, 'message' => 'Fill in hostname, database name, username and password first.'];
            }
        }
        $sourceDBName = $this->pdo->query('select database()')->fetchColumn();
        if (strcasecmp((string) $sourceDBName, trim((string) $dataSetting['targetSchemaName'])) == 0) {
            return ['ok' => false, 'message' => "The target database must differ from the CSWeb metadata database ($sourceDBName)."];
        }
        $dataSetting['targetSchemaName'] = trim((string) $dataSetting['targetSchemaName']);
        $params = $this->buildConnectionParams($dataSetting);
        // Fail fast instead of hanging on an unreachable host.
        if (in_array($params['driver'], ['pdo_pgsql', 'pdo_mysql'], true)) {
            $params['driverOptions'] = [\PDO::ATTR_TIMEOUT => 5];
        }
        $target = $params['host'] . (isset($params['port']) ? ':' . $params['port'] : '') . ' / ' . $params['dbname'];
        try {
            $conn = DriverManager::getConnection($params, new Configuration());
            $conn->fetchOne('SELECT 1');
            $class = (new \ReflectionClass($conn->getDatabasePlatform()))->getShortName();
            $platform = str_contains($class, 'MariaDb') ? 'MariaDB'
                : (str_contains($class, 'MySQL') ? 'MySQL'
                : (str_contains($class, 'PostgreSQL') ? 'PostgreSQL'
                : (str_contains($class, 'SQLServer') ? 'SQL Server' : $class)));
            try {
                $native = $conn->getWrappedConnection();
                if (method_exists($native, 'getServerVersion')) {
                    $version = (string) $native->getServerVersion();
                    if (preg_match('/\d+\.\d+(\.\d+)?/', $version, $m)) {
                        $platform .= ' ' . $m[0];
                    }
                }
            } catch (\Throwable $ignored) {
                // The version is a nicety; the connection itself succeeded.
            }
            $conn->close();
            return ['ok' => true, 'message' => "Connected to $target.", 'server' => $platform];
        } catch (\Throwable $e) {
            $this->logger->warning('Test connection failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => self::explainConnectionError($e->getMessage(), $target)];
        }
    }

    /** Map the common driver errors to a hint the administrator can act on. */
    public static function explainConnectionError(string $raw, string $target): string {
        $hints = [
            '/password authentication failed|Access denied for user|Login failed for user|28P01|\[1045\]|18456/i'
                => 'Wrong username or password.',
            '/database "[^"]*" does not exist|Unknown database|3D000|\[1049\]|Cannot open database/i'
                => 'The database does not exist on this server. Create it first (the breakout does not create the database).',
            '/could not translate host name|getaddrinfo|Name or service not known|php_network_getaddresses|Unknown (MySQL server )?host/i'
                => 'Hostname not found. With Docker, use the service name (postgres, mysql-breakout, sqlserver).',
            '/Connection refused|\[2002\]|08006|\[111\]|actively refused/i'
                => 'Nothing answers on this host and port. Check the port: with Docker, use the internal port (5432, 3306, 1433), not the published one (e.g. 3307).',
            '/timed out|timeout expired|\[110\]/i'
                => 'The server did not answer in time (firewall, wrong host or port).',
            '/could not find driver/i'
                => 'The PHP driver for this database type is not installed (see csweb:check-database-drivers).',
            '/SSL|certificate/i'
                => 'The server requires an SSL/TLS connection that is not configured.',
        ];
        foreach ($hints as $pattern => $hint) {
            if (preg_match($pattern, $raw)) {
                return "Cannot connect to $target. $hint";
            }
        }
        $short = preg_replace('/^.*?(SQLSTATE\[[^\]]+\].*)$/s', '$1', $raw);
        return "Cannot connect to $target. " . mb_substr(trim($short), 0, 200);
    }

    public function addDataSetting($dataSetting): bool {
        $bind = [];
        $sourceDBName = $this->pdo->query('select database()')->fetchColumn();
        $dataSetting['targetSchemaName'] = trim($dataSetting['targetSchemaName']);
        $dataSetting['dbPassword'] = trim($dataSetting['dbPassword']);
        if (strcasecmp($sourceDBName, $dataSetting['targetSchemaName']) == 0) {
            throw new \Exception("Source database: $sourceDBName cannot be same as  Target database: " . $dataSetting['targetSchemaName']);
        }
        // Community layer: honour the per-dictionary driver, port and tunnel
        // mode when testing the connection, so "test connection" exercises the
        // exact same path the breakout will later use.
        $connectionParams = $this->buildConnectionParams($dataSetting);
        $config = new Configuration();
        try {
            $conn = DriverManager::getConnection($connectionParams, $config);
            $isConnected = $conn->connect();
//if connection successful add
            if ($isConnected) {
                // Community layer: `port` and `db_type` are fork-only columns
                // (community schema migrations) that let a dictionary target a
                // non-default port and a PostgreSQL / SQL Server backend.
                $stm = "INSERT INTO `cspro_dictionaries_schema`(`dictionary_id`, `host_name`, `port`, `db_type`, `schema_name`, `schema_user_name`, `schema_password`, `additional_config`, `map_info`) "
                        . "VALUES (:id, :targetHostName, :targetPort, :dbType, :targetSchemaName, :dbUserName,AES_ENCRYPT(:dbPassword, :keyString), :additionalConfig, :mapInfo)";

                $bind['id'] = $dataSetting['id'];
                $bind['targetHostName'] = $dataSetting['targetHostName'];
                $bind['targetPort'] = ($dataSetting['targetPort'] ?? '') !== '' ? (int) $dataSetting['targetPort'] : null;
                $bind['dbType'] = $dataSetting['dbType'] ?? 'postgresql';
                $bind['targetSchemaName'] = $dataSetting['targetSchemaName'];
                $bind['dbUserName'] = $dataSetting['dbUserName'];
                $bind['dbPassword'] = $dataSetting['dbPassword'];
                $bind['additionalConfig'] = json_encode($dataSetting['additionalConfig'], JSON_THROW_ON_ERROR);
                $bind['mapInfo'] = json_encode($dataSetting['mapInfo'], JSON_THROW_ON_ERROR);
                $bind['keyString'] = 'cspro';
                $stmt = $this->pdo->prepare($stm);
                $stmt->execute($bind);
                $this->updateDictionarySchema($dataSetting['id']);
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed adding configuration: " . $e->getMessage());
            throw $e;
        }
        return $conn !== null;
    }

    public function updateDataSetting($dataSetting): bool {
        $bind = [];
        $sourceDBName = $this->pdo->query('select database()')->fetchColumn();
        $dataSetting['targetSchemaName'] = trim($dataSetting['targetSchemaName']);
        $dataSetting['dbPassword'] = trim($dataSetting['dbPassword']);
        if (strcasecmp($sourceDBName, $dataSetting['targetSchemaName']) == 0) {
            throw new \Exception("Source database: $sourceDBName cannot be same as  Target database: " . $dataSetting['targetSchemaName']);
        }
        $this->logger->debug('setting is ' . print_r($dataSetting,true));
        // Community layer: honour the per-dictionary driver, port and tunnel
        // mode when testing the connection, so "test connection" exercises the
        // exact same path the breakout will later use.
        $connectionParams = $this->buildConnectionParams($dataSetting);
        $config = new Configuration();
        try {
            $conn = DriverManager::getConnection($connectionParams, $config);
            $isConnected = $conn->connect();
//if connection successful add
            if ($isConnected) {
                $hasProcessCasesUpdateOccurred = $this->hasProcessCasesOptionsUpdated($dataSetting);
              
                // Community layer: also persist the fork-only `port` and
                // `db_type` columns.
                $stm = "UPDATE `cspro_dictionaries_schema` SET `host_name` =  :targetHostName, `port` = :targetPort, `db_type` = :dbType, `schema_name` =  :targetSchemaName,"
                        . " `schema_user_name` = :dbUserName, `schema_password` = AES_ENCRYPT(:dbPassword, :keyString), "
                        . " `additional_config` = :additionalConfig, `map_info` = :mapInfo"
                        . " WHERE `dictionary_id` = :id";

                $bind['id'] = $dataSetting['id'];
                $bind['targetHostName'] = $dataSetting['targetHostName'];
                $bind['targetPort'] = ($dataSetting['targetPort'] ?? '') !== '' ? (int) $dataSetting['targetPort'] : null;
                $bind['dbType'] = $dataSetting['dbType'] ?? 'postgresql';
                $bind['targetSchemaName'] = $dataSetting['targetSchemaName'];
                $bind['dbUserName'] = $dataSetting['dbUserName'];
                $bind['dbPassword'] = $dataSetting['dbPassword'];
                $bind['additionalConfig'] = json_encode($dataSetting['additionalConfig'], JSON_THROW_ON_ERROR);
                $bind['mapInfo'] = json_encode($dataSetting['mapInfo'], JSON_THROW_ON_ERROR);
                $bind['keyString'] = 'cspro';
                $stmt = $this->pdo->prepare($stm);
                $stmt->execute($bind);
                
                if ($hasProcessCasesUpdateOccurred) {
                    $this->updateDictionarySchema($dataSetting['id']);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed updating configuration: " . $e->getMessage());
            throw $e;
        }
        return $conn !== null;;
    }

    public function getDataCounts(&$dataSettings) {
//get each dictionary get the counts in the source and target schema
        foreach ($dataSettings as &$dataSetting) {
            $dataSetting['totalCases'] = "";
            $dataSetting['processedCases'] = "";
            $dataSetting['lastProcessedTime'] = "";
            $dataSetting['lastError'] = "";

            if (isset($dataSetting['targetSchemaName'])) {
                // Community layer: breakout tables live in the TARGET database
                // prefixed per dictionary, because several dictionaries can share
                // one target schema (selective breakout). Upstream assumes one
                // dictionary per schema and queries bare `cases` / `cspro_jobs`.
                //
                // The prefix is always lowercased: the schema generator and the
                // serializer both strtolower it. On case-sensitive MySQL (Linux,
                // lower_case_table_names=0) dropping the strtolower yields
                // "DARA_USERS_cases" instead of "dara_users_cases" -> error 42S02,
                // swallowed by the catch below -> processedCases stays 0 in the UI
                // even though the breakout succeeded. Keep it aligned with
                // BreakoutStatusService.
                $name_dict = strtolower(str_replace(" ", "_", str_replace("_DICT", "", $dataSetting['name']))) . "_";

                $stm = "SELECT count(*) FROM `" . $dataSetting['name'] . "` WHERE `deleted` = 0";
                $caseCount = (int) $this->pdo->fetchValue($stm);
                $dataSetting['totalCases'] = $caseCount;

//get number of cases processsed.
                $connectionParams = $this->buildConnectionParams($dataSetting);
                $config = new Configuration();
                try {
                    $conn = DriverManager::getConnection($connectionParams, $config);

//get processsed case count
                    $dataSetting['processedCases'] = 0;
                    $statement = $conn->executeQuery('SELECT count(*) FROM ' . $name_dict . 'cases where deleted=0');
                    $processedCases = $statement->fetchOne();
                    $dataSetting['processedCases'] = $processedCases;

//get processed time (modified time) from the most recently processed job
                    $statement = $conn->executeQuery('SELECT id , modified_time FROM ' . $name_dict . 'cspro_jobs WHERE id = (SELECT max(id) from ' . $name_dict . 'cspro_jobs where status =2)');
                    if (($row = $statement->fetchAssociative()) !== false) {
                        $dataSetting['lastProcessedTime'] = $row['modified_time'];
                    }
                } catch (\Exception $e) {
                    if (strpos((string) $e, 'SQLSTATE[42S02]') == FALSE) {
                        $this->logger->error('Failed getting case counts and last processed time', ["context" => (string) $e]);
                    }
                }

                // Community layer: surface the last FAILED job's message (status
                // 3 = FAILED). Isolated query: on a deployment not yet migrated
                // the error_message column may be missing, so failure here is
                // ignored silently rather than losing the counters above.
                try {
                    // isset($conn): $conn is assigned in the try above; if
                    // getConnection() itself threw (target unreachable), $conn
                    // would be undefined and $conn->executeQuery would raise an
                    // \Error that catch (\Exception) would not catch.
                    if (isset($conn)) {
                        $statement = $conn->executeQuery('SELECT error_message FROM ' . $name_dict . 'cspro_jobs WHERE id = (SELECT max(id) from ' . $name_dict . 'cspro_jobs where status = 3)');
                        if (($row = $statement->fetchAssociative()) !== false && !empty($row['error_message'])) {
                            $dataSetting['lastError'] = (string) $row['error_message'];
                        }
                    }
                } catch (\Throwable $e) {
                    // column or table absent on an un-migrated target: not fatal
                }
            }
        }
        return $dataSettings;
    }
    
    function hasProcessCasesOptionsUpdated($dataSetting): bool {
        try {
            $dictionaryId = $dataSetting['id'];
            $stm = 'SELECT `additional_config` FROM `cspro_dictionaries_schema` WHERE `dictionary_id` = :id';
            $bind = [];
            $bind['id'] = $dictionaryId;
            $currentAdditionalConfig = $this->pdo->fetchValue($stm, $bind);
            $currentProcessCasesOptions = "";
            if ($currentAdditionalConfig !== "null") {
                $currentAdditionalConfig = json_decode($currentAdditionalConfig, true);
                // todo: the key 'processCasesOptions' will exist. otherwise, the upload of the additional configuration options will be rejected by the json validation.
                $currentProcessCasesOptions = $currentAdditionalConfig['processCasesOptions'];
                $currentProcessCasesOptions = json_encode($currentProcessCasesOptions);
            }

            $newProcessCasesOptions = "";
            if (isset($dataSetting['additionalConfig'])) {
                $newAdditionalConfig = $dataSetting['additionalConfig'];
                // todo: the key 'processCasesOptions' will exist. otherwise, the upload of the additional configuration options will be rejected by the json validation.
                $newProcessCasesOptions = $newAdditionalConfig['processCasesOptions'];
                $newProcessCasesOptions = json_encode($newProcessCasesOptions);
            }

            if ($currentProcessCasesOptions === $newProcessCasesOptions) {
                // they're the same. nothing to do.
                return false;
            }
            else {
                // they're different. the data settings scheme will need to be recreated.
                return true;
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to compare current and old process cases options. Dictionary Id: ' . $dictionaryId, ["context" => (string) $e]);
            throw $e;    
        }
    }
    
    function updateDictionarySchema($dictionaryId) {
        try {
            // get dictionary name
            $stm = 'SELECT `name` FROM `cspro_dictionaries` WHERE `id` = :id';
            $bind = [];
            $bind['id'] = $dictionaryId;
            $dictName = $this->pdo->fetchValue($stm, $bind);

            // drop tables and recreate them. this will delete data (processed cases).
            $dictionarySchemaHelper = new DictionarySchemaHelper($dictName, $this->pdo, $this->logger);
            $dictionarySchemaHelper->regenerateSchema();
        } catch (\Exception $e) {
            $this->logger->error('Failed recreating data setting schema. Dictionary Id: ' . $dictionaryId, ["context" => (string) $e]);
            throw $e;
        }
    }
    
    function deleteDataSetting($dictionaryId): bool {
        try {
            // get dictionary name
            $stm = 'SELECT `name` FROM `cspro_dictionaries` WHERE `id` = :id';
            $bind = [];
            $bind['id'] = $dictionaryId;
            $dictName = $this->pdo->fetchValue($stm, $bind);

            // Build the helper before the DELETE: it reads the connection
            // parameters from cspro_dictionaries_schema, the very row about to
            // be removed.
            $dictionarySchemaHelper = new DictionarySchemaHelper($dictName, $this->pdo, $this->logger);
            $canDropTables = false;
            try {
                $canDropTables = $dictionarySchemaHelper->initialize();
            } catch (\Throwable $connect) {
                $this->logger->warning(
                    'Target database unreachable for ' . $dictName . ', its tables will be left in place.',
                    ['context' => (string) $connect]
                );
            }

            // Community layer: delete the configuration row FIRST.
            //
            // Upstream called regenerateSchema() before the DELETE, which drops
            // the target tables and recreates them. When that recreation fails —
            // an unreachable target, or a record wide enough to hit InnoDB's
            // 8126-byte row limit — the exception propagates and the DELETE
            // never runs. The configuration then cannot be removed from the UI
            // at all: the Delete button confirms, posts, and silently fails,
            // leaving the operator stuck with a broken entry.
            //
            // Removing the row first makes the button always work. Dropping the
            // target tables is cleanup, so it is attempted afterwards and its
            // failure is logged rather than fatal.
            $stm = 'DELETE FROM `cspro_dictionaries_schema` WHERE `dictionary_id` = :id';
            $row_count = $this->pdo->fetchAffected($stm, $bind);

            if ($canDropTables) {
                try {
                    $dictionarySchemaHelper->dropSchema();
                } catch (\Throwable $cleanup) {
                    $this->logger->warning(
                        'Configuration removed, but the target tables could not be dropped for ' . $dictName
                        . ' — they may need dropping by hand.',
                        ['context' => (string) $cleanup]
                    );
                }
            }

            return (bool) $row_count;
        } catch (\Exception $e) {
            $this->logger->error('Failed deleting configuration. Dictionary Id: ' . $dictionaryId, ["context" => (string) $e]);
            throw $e;
        }
    }

}
