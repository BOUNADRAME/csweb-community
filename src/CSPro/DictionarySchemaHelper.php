<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\CSPro;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;
use Psr\Log\LoggerInterface;
use App\CSPro\Dictionary\MySQLDictionarySchemaGenerator;
use App\CSPro\DictionaryHelper;
use App\CSPro\Dictionary\Dictionary;
use App\CSPro\Dictionary\Record;
use Doctrine\DBAL\Schema;
use App\Service\PdoHelper;
use App\CSPro\Data\DataSettings;
use App\CSPro\Data\MySQLQuestionnaireSerializer;

/**
 * Description of DictionarySchemaHelper
 *
 * @author savy
 */
class DictionarySchemaHelper {

    public const JOB_STATUS_NOT_STARTED = '0';
    public const JOB_STATUS_IN_PROCESS = '1';
    public const JOB_STATUS_COMPLETE = '2';
    // Community layer: upstream has no terminal failure state, so a crashed
    // breakout stayed IN_PROCESS forever and read as "still running".
    public const JOB_STATUS_FAILED = '3';

    private $conn;
    private $config;
    private $dictionary;
    private $connectionParams;
    private $initialized;

    public function __construct(private string $dictionaryName, private PdoHelper $pdo, private LoggerInterface $logger) {
        $this->initialized = false;
        $this->dictionary = null;
        $this->connectionParams = null;
        $this->conn = null;
        $this->config = null;
    }

    private function getConnectionParameters(): bool {
        // Community layer: also read the fork-only `port` and `db_type` columns.
        $stm = "SELECT host_name, port, db_type, schema_name, schema_user_name, AES_DECRYPT(schema_password, '" . "cspro') as `password` FROM `cspro_dictionaries_schema`"
            . " JOIN `cspro_dictionaries` ON dictionary_id = cspro_dictionaries.id WHERE cspro_dictionaries.name = :dictName";
        $bind = ['dictName' => $this->dictionaryName];

        $result = $this->pdo->fetchOne($stm, $bind);

        if ($result) {
            // Community layer: upstream hardcodes pdo_mysql and ignores the
            // port, so every breakout target had to be a MySQL server on the
            // default port. Resolve the driver from the dictionary's db_type,
            // and the effective host/port from the connection mode (direct =
            // use host_name as-is, tunnel = rewrite to 127.0.0.1:tunnel-port).
            $driver = DataSettings::resolveDriver($result['db_type'] ?? 'postgresql');
            $resolved = \App\Service\BreakoutConnectionResolver::resolve($result);
            $this->connectionParams = [
                'dbname'   => $result['schema_name'],
                'user'     => $result['schema_user_name'],
                'password' => $result['password'],
                'host'     => $resolved['host'],
                'driver'   => $driver,
            ];
            // Omit the port when unset so DBAL falls back to the driver default.
            if ($resolved['port'] !== null) {
                $this->connectionParams['port'] = $resolved['port'];
            }
            return true;
        } else {
            $this->connectionParams = null;
            $this->logger->info('Database information not found for dictionary: ' . $this->dictionaryName);
            return false;
        }
    }

    public static function updateProcessCasesOptions(Dictionary $dictionary, $processCasesOptions, LoggerInterface $logger) {
        //for each level 
        for ($iLevel = 0; $iLevel < (is_countable($dictionary->getLevels()) ? count($dictionary->getLevels()) : 0); $iLevel++) {
            $level = $dictionary->getLevels()[$iLevel];
            //level ids are always included they use the default value of true. No need to process
            for ($iRecord = 0; $iRecord < (is_countable($level->getRecords()) ? count($level->getRecords()) : 0); $iRecord++) {
                $record = $level->getRecords()[$iRecord];
                $isRecordIncluded = self::isRecordIncluded($record, $processCasesOptions);
                $record->includeInBlobBreakOut($isRecordIncluded);
                $itemFromRecordIncluded = false;
                $recordName = $record->getName();
                $isRecordIncluded ? $logger->debug("Included Record $recordName") :$logger->debug("Excluded Item $recordName") ;
                for ($iItem = 0; $iItem < (is_countable($record->getItems()) ? count($record->getItems()) : 0); $iItem++) {
                    $item = $record->getItems()[$iItem];
                    $isItemIncluded = self::isItemIncluded($item, $processCasesOptions, $isRecordIncluded);
                    $item->includeInBlobBreakOut($isItemIncluded);
                    $itemName = $item->getName();
                    $isItemIncluded ? $logger->debug("Included Item $itemName") :$logger->debug("Excluded Item $itemName") ;
                    if($isItemIncluded){
                        $itemFromRecordIncluded = true;
                    }
                }
                //finally set the flag for the record to be included to true in the blob breakout if any item from record is included
                if($itemFromRecordIncluded){
                    $record->includeInBlobBreakOut($itemFromRecordIncluded);
                    $logger->debug("Included Record $recordName");
                }
            }
        }
    }

    public static function isRecordIncluded(Record $record, $processCasesOptions): bool {
        //record is included if it is included or any of the items are included
        $includeOptions = isset($processCasesOptions['include']) ? $processCasesOptions['include'] : null;
        $excludeOptions = isset($processCasesOptions['exclude']) ? $processCasesOptions['exclude'] : null;
        //if the include options are not set or is empty  or name is found the include list set included to true
        $name = $record->getName();
        $isRecordincluded = false;
        if ($includeOptions === null || count($includeOptions) === 0 || in_array(strtoupper($name), array_map('strtoupper', $includeOptions), true)) {
            $isRecordincluded = true;
        }
        //if the name if found in the excluded list set the included flag to false
        if (isset($excludeOptions) && count($excludeOptions) > 0 && in_array(strtoupper($name), array_map('strtoupper', $excludeOptions), true)) {
            $isRecordincluded = false;
        }
        return $isRecordincluded;
    }

    //if record is included in processing then call with parentIncluded to true, so that the item is also included by default
    public static function isItemIncluded($item, $processCasesOptions, $recordIncluded = false): bool {
        $included = $recordIncluded;
        $name = $item->getName();   
        $includeOptions = isset($processCasesOptions['include']) ? $processCasesOptions['include'] : null;
        $excludeOptions = isset($processCasesOptions['exclude']) ? $processCasesOptions['exclude'] : null;
        //if record is not included and include options is set then check if the item is included
        if (($included === false) && isset($includeOptions) && (count($includeOptions) > 0)) {
            $included = (array_search(strtoupper($name), array_map('strtoupper', $includeOptions)) !== false);
        }
        //if the name if found in the excluded list set the included flag to false
        if (isset($excludeOptions) && count($excludeOptions) > 0 && array_search(strtoupper($name), array_map('strtoupper', $excludeOptions)) !== false) {
            $included = false;
        }
        return $included;
    }

    public function initialize($checkDictionarySchema = false): bool {
//get the connection parameters
        /* Provide DBAL with some initial database infor */
        if ($this->initialized == true) { //allow init to be done only once to prevent gc
            return $this->initialized;
        }
        $this->config = new Configuration();
        try {
//load dictionary
            $dbConfigSettings = new DBConfigSettings($this->pdo, $this->logger);
            $serverDeviceId = $dbConfigSettings->getServerDeviceId(); //server name
            $dictionaryHelper = new DictionaryHelper($this->pdo, $this->logger, $serverDeviceId);
            $this->dictionary = $dictionaryHelper->loadDictionary($this->dictionaryName);

            /* Connect to the database */
            $this->initialized = $this->getConnectionParameters();
            if ($this->initialized == false) {
                return $this->initialized;
            }
            $this->conn = DriverManager::getConnection($this->connectionParams, $this->config);
            if ($checkDictionarySchema && !$this->IsValidSchema()) { //thread never should call using checkDictionarySchema as true
//drop all the tables that exist.
                $this->cleanDictionarySchema();
                $processCasesOptions = $this->getProcessCaseOptions();
                $this->createDictionarySchema($processCasesOptions);
            }
            // Community layer: idempotent auto-migration adding error_message to
            // _cspro_jobs on deployments created before that column existed.
            // The target database has no schema_version of its own, so this is
            // detected by introspection. Never rebuilds the schema.
            $this->ensureJobsErrorColumn();
        } catch (\Exception $e) {
            $strMsg = "Failed initializing database: " . $this->connectionParams['dbname'] . " while processsing Dictionary: " . $this->dictionaryName;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw $e;
        }
        $this->initialized = true;
        return $this->initialized;
    }

    public function regenerateSchema(): bool {
        //get the connection parameters
        $this->config = new Configuration();
        try {
            // load dictionary
            $dbConfigSettings = new DBConfigSettings($this->pdo, $this->logger);
            $serverDeviceId = $dbConfigSettings->getServerDeviceId(); //server name
            $dictionaryHelper = new DictionaryHelper($this->pdo, $this->logger, $serverDeviceId);
            $this->dictionary = $dictionaryHelper->loadDictionary($this->dictionaryName);

            // connect to the database
            $this->initialized = $this->getConnectionParameters();
            if ($this->initialized == false) {
                return $this->initialized;
            }
            $this->conn = DriverManager::getConnection($this->connectionParams, $this->config);

            // drop all the tables that exist and recreate them
            $this->cleanDictionarySchema();
            $processCasesOptions = $this->getProcessCaseOptions();
            $this->createDictionarySchema($processCasesOptions);
        } catch (\Exception $e) {
            $strMsg = "Failed clearing database: " . $this->connectionParams['dbname'] . " for associated Dictionary: " . $this->dictionaryName;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw $e;
        }
        $this->initialized = true;
        return $this->initialized;
    }

    private function cleanDictionarySchema() {
        try {
            $tables = $this->conn->getSchemaManager()->listTables();
            if ((is_countable($tables) ? count($tables) : 0) > 0) {
                $this->conn->prepare("SET FOREIGN_KEY_CHECKS = 0;")->execute();

                foreach ($tables as $table) {
                    $sql = 'DROP TABLE ' . MySQLDictionarySchemaGenerator::quoteString($table->getName());
                    $this->conn->prepare($sql)->execute();
                }
                $this->conn->prepare("SET FOREIGN_KEY_CHECKS = 1;")->execute();
            }
        } catch (\Exception $e) {
            $strMsg = "Failed deleting tables from database: " . $this->connectionParams['dbname'] . " while processsing Dictionary: " . $this->dictionaryName;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw $e;
        }
    }

    private function createDictionarySchema($processCasesOptions) {

        $bind = [];
        try {
            $dictionarySchema = new MySQLDictionarySchemaGenerator($this->logger);
            $processCasesOptions = $this->getProcessCaseOptions();
            $schema = $dictionarySchema->generateDictionary($this->dictionary, $processCasesOptions);
            $dictionarySQL = $schema->toSql($this->conn->getDatabasePlatform());
            $dictionarySQL = implode(";" . PHP_EOL, $dictionarySQL);
            $this->logger->debug("writing schema SQL " . $dictionarySQL);

            $this->conn->prepare($dictionarySQL)->execute();

            //insert into cspro_meta dictionary information
            $dictionaryVersion = $this->dictionary->getVersion();
            $stm = "SELECT modified_time, `dictionary_full_content` FROM `cspro_dictionaries` "
                    . " WHERE  name = '" . $this->dictionaryName . "'";
            $result = $this->pdo->fetchOne($stm);
            if ($result) {
                $stm = "INSERT INTO `cspro_meta`(`cspro_version`, `dictionary`, `source_modified_time`) "
                        . "VALUES (:version, :dictionary, :source_modified_time)";
                $bind['version'] = $dictionaryVersion;
                $bind['dictionary'] = $result['dictionary_full_content'];
                $bind['source_modified_time'] = $result['modified_time'];
                $stmt = $this->conn->executeUpdate($stm, $bind);
            }
        } catch (\Exception $e) {
            $strMsg = "Failed generating tables in database: " . $this->connectionParams['dbname'] . " while processsing Dictionary: " . $this->dictionaryName;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw $e;
        }
    }

    public function getProcessCaseOptions(): array {
        $result = Array();
        $dataSettings = new DataSettings($this->pdo, $this->logger);
        $dataSetting = $dataSettings->getDataSetting($this->dictionary->getName(), false);
        $additionalConfig = isset($dataSetting['additionalConfig']) ? json_decode($dataSetting['additionalConfig'], true, 512, JSON_THROW_ON_ERROR) : null;
        if (isset($additionalConfig['processCasesOptions'])) {
            $result = $additionalConfig['processCasesOptions'];
        }
        return $result;
    }

    public function tableExists($table) {
        // Community layer: ask the schema manager instead of probing with
        // "SELECT 1 ... LIMIT 1". LIMIT is MySQL/PostgreSQL syntax and a
        // syntax error on SQL Server, so the probe threw on every call there
        // and the table always looked missing. tablesExist() resolves through
        // the platform's own catalogue, so it works on all three engines.
        try {
            return $this->conn->getSchemaManager()->tablesExist([trim($table, '`"[]')]);
        } catch (\Exception) {
            return false;
        }
    }

    public function IsValidSchema(): bool {
        $bind = [];
        //check the time stamp of dictionary in the meta table with the original dictionary timestamp.
        $isValid = false;
        try {
            if (!$this->tableExists("`cspro_meta`")) {
                return $isValid;
            }
            $stm = "SELECT source_modified_time FROM `cspro_meta` ";
            $stmt = $this->conn->executeQuery($stm);
            $result = $stmt->fetch();
            if ($result) {
                $stm = "SELECT count(*) FROM `cspro_dictionaries` "
                        . " WHERE  name = :dictionaryName and `modified_time` = :source_modified_time";
                $bind['dictionaryName'] = $this->dictionaryName;
                $bind['source_modified_time'] = $result['source_modified_time'];

                $result = (int) $this->pdo->fetchValue($stm, $bind);
                $isValid = ($result === 1) ? true : false;
            }
        } catch (\Exception $e) {
            $strMsg = "Failed validating schema  " . $this->connectionParams['dbname'] . " while processsing Dictionary: " . $this->dictionaryName;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw $e;
        }
        $this->logger->debug('The schema valid flag is ' . $isValid);
        return $isValid;
    }

    //reset in process jobs to not started at the start of the long process to be picked up again
    public function resetInProcesssJobs(): int {
        $bind = [];
        try {
            // Community layer: reset FAILED jobs alongside IN_PROCESS ones.
            // A job left in FAILED is never picked up again, so its cases would
            // stay unprocessed forever — the failure state exists to be visible,
            // not to be terminal. The table is prefixed per dictionary, and the
            // identifiers are quoted through the target platform so the same
            // statement runs on MySQL, PostgreSQL and SQL Server.
            $dictionaryLabel = strtolower(str_replace(" ", "_", str_replace("_DICT", "", $this->dictionary->getName())));
            $platform = $this->conn->getDatabasePlatform();
            $qi = fn(string $id): string => $platform->quoteIdentifier($id);

            $stm = 'UPDATE ' . $qi($dictionaryLabel . '_cspro_jobs')
                 . ' SET ' . $qi('status') . ' = :status'
                 . ' WHERE ' . $qi('status') . ' IN (:in_process_jobs, :failed_jobs)';
            $bind['status'] = self::JOB_STATUS_NOT_STARTED;
            $bind['in_process_jobs'] = self::JOB_STATUS_IN_PROCESS;
            $bind['failed_jobs'] = self::JOB_STATUS_FAILED;
            $count = $this->conn->executeUpdate($stm, $bind);
        } catch (\Exception $e) {
            $strMsg = "Failed resetting jobs in schema  " . $this->connectionParams['dbname'] . " while processsing Dictionary: " . $this->dictionaryName;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw $e;
        }
        return $count;
    }

    public function processNextJob($maxCasesPerChunk): int {
        $bind = [];
        $jobId = 0;
//find a job that is not being processed and update its status to processing
        try {
            // Community layer: claim the job atomically.
            //
            // Upstream reads the next NOT_STARTED job and marks it IN_PROCESS
            // in two separate statements, with nothing in between. Two workers
            // reaching that gap together both see the same row and both process
            // the same cases — duplicated writes into the target database.
            //
            // The SELECT now runs inside a transaction with FOR UPDATE, so the
            // second worker blocks on the row until the first commits, then
            // re-reads it as IN_PROCESS and moves on. Single-worker deployments
            // — the current scheduler runs dictionaries sequentially under a
            // LockableTrait lock — behave exactly as before: an uncontended row
            // lock costs nothing.
            $this->conn->beginTransaction();
            try {
                // Row limit and lock clause both come from the platform:
                // "LIMIT 1 FOR UPDATE" on MySQL and PostgreSQL,
                // "OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY" on SQL Server, where
                // LIMIT is a syntax error.
                $platform = $this->conn->getDatabasePlatform();
                $stm = $platform->modifyLimitQuery(
                    'SELECT ' . $this->qi('id') . ' FROM ' . $this->qt('cspro_jobs')
                    . ' WHERE ' . $this->qi('status') . ' = ' . self::JOB_STATUS_NOT_STARTED
                    . ' ORDER BY ' . $this->qi('id'),
                    1
                ) . ' ' . $platform->getWriteLockSQL();
                $stmt = $this->conn->prepare($stm);
                $resultSet = $stmt->execute();
                $result = $resultSet->fetchAllAssociative();
                $jobId = (is_countable($result) ? count($result) : 0) > 0 ? (int) $result[0]['id'] : 0;

                if ($jobId) {
                    $stm = 'UPDATE ' . $this->qt('cspro_jobs') . ' SET ' . $this->qi('status') . ' = :status'
                            . ' WHERE ' . $this->qi('id') . ' = :id';
                    $bind['status'] = self::JOB_STATUS_IN_PROCESS;
                    $bind['id'] = $jobId;
                    $this->conn->executeUpdate($stm, $bind);
                }
                $this->conn->commit();
            } catch (\Exception $inner) {
                if ($this->conn->isTransactionActive()) {
                    $this->conn->rollBack();
                }
                throw $inner;
            }

            // No pending job: create one *outside* the transaction above, so the
            // row lock is released first. createJob() scans the source database
            // and can be slow; holding the lock across it would serialise
            // workers for no benefit.
            if (!$jobId) {
                $jobId = $this->createJob($maxCasesPerChunk);
                if ($jobId) {
                    $stm = 'UPDATE ' . $this->qt('cspro_jobs') . ' SET ' . $this->qi('status') . ' = :status'
                            . ' WHERE ' . $this->qi('id') . ' = :id';
                    $this->conn->executeUpdate($stm, [
                        'status' => self::JOB_STATUS_IN_PROCESS,
                        'id' => $jobId,
                    ]);
                }
            }
        } catch (\Exception $e) {
            $strMsg = "Failed getting next job from database:  " . $this->connectionParams['dbname'] . " while processsing Dictionary: " . $this->dictionaryName;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw $e;
        }
        return $jobId;
    }

    public function createJob($maxCasesPerChunk): int {
        $bind = [];
        //if a job already exists - get the endCaseId and endRevision if there are no cases at this revision 
//SELECT the most recent job and get the endCaseId and endRevision 
        $jobId = 0;
        $jobColumns = ['id', 'start_caseid', 'start_revision', 'end_caseid', 'end_revision', 'cases_processed', 'status'];
        // Community layer: platform-generated row limit, see processNextJob().
        $stm = $this->conn->getDatabasePlatform()->modifyLimitQuery(
            'SELECT ' . implode(', ', array_map(fn($c) => $this->qi($c), $jobColumns))
            . ' FROM ' . $this->qt('cspro_jobs')
            . ' ORDER BY ' . $this->qi('id') . ' DESC',
            1
        );

        try {
            $stmt = $this->conn->prepare($stm);
            $resultSet = $stmt->execute();
            $result = $resultSet->fetchAllAssociative();
            $endRevision = 0;
            $endCaseId = 0;
            if ($result) {
                $endRevision = $result[0]['end_revision'];
                $endCaseId = $result[0]['end_caseid'];
            }
//select cases from the source cases  table  where revision = end_revision  end_revision id > end_caseid 
            $stm = "SELECT `id`, `revision` FROM " . $this->dictionaryName . " WHERE revision = :endRevision and `id` > :endCaseId  "
                    . " UNION "
                    . "SELECT `id`, `revision` FROM " . $this->dictionaryName . " WHERE revision > :endRevision "
                    . "ORDER BY `revision`, `id` LIMIT :limit";

            $limit = $maxCasesPerChunk;
            $stmt = $this->pdo->prepare($stm);
            $stmt->bindParam(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindParam(':endCaseId', $endCaseId, \PDO::PARAM_INT);
            $stmt->bindParam(':endRevision', $endRevision, \PDO::PARAM_INT);

            $stmt->execute();
            $result = $stmt->fetchAll();
            if ($result) {
                unset($bind);
                $bind['startCaseId'] = $result[0]['id'];
                $bind['startRevision'] = $result[0]['revision'];
                $bind['endCaseId'] = $result[count($result) - 1]['id'];
                $bind['endRevision'] = $result[count($result) - 1]['revision'];
                $bind['cases_to_process'] = count($result);
                $insertColumns = ['start_caseid', 'start_revision', 'end_caseid', 'end_revision', 'cases_to_process'];
                $stm = 'INSERT INTO ' . $this->qt('cspro_jobs')
                        . '(' . implode(', ', array_map(fn($c) => $this->qi($c), $insertColumns)) . ') '
                        . 'VALUES (:startCaseId, :startRevision, :endCaseId, :endRevision, :cases_to_process)';
                $stmt = $this->conn->executeUpdate($stm, $bind);
                $jobId = $this->conn->lastInsertId();
            }
            return $jobId;
        } catch (\Exception $e) {
            $strMsg = "Failed creating job in database:  " . $this->connectionParams['dbname'] . " while processsing Dictionary: " . $this->dictionaryName;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw $e;
        }
    }

    public function blobBreakOut($jobId, $processCasesOptions) {
        //$dictionary, PdoHelper $sourceDB, $targetDB, $jobID 
        //select cases from sourceDB and generate insertSQL to insert/update the case 
        try {
            $questionnaireSerializer = new MySQLQuestionnaireSerializer($this->dictionary, $jobId, $this->pdo, $this->conn, $this->logger);
            $questionnaireSerializer->serializeQuestionnaries($processCasesOptions);
        } catch (\Exception $e) {
            $strMsg = "Failed processing questionnaires for JobId: " . $jobId . " in database:  " . $this->connectionParams['dbname'] . " while processsing Dictionary: " . $this->dictionaryName;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw new \Exception($strMsg, 0, $e);
        }
    }

    /**
     * Community layer: per-dictionary table prefix in the TARGET database.
     *
     * Upstream assumes one dictionary per target schema and queries bare
     * `cases` / `cspro_jobs`. Selective breakout lets several dictionaries share
     * one schema, so every table is prefixed. Must stay byte-identical to the
     * prefix computed by MySQLDictionarySchemaGenerator (DDL) and
     * MySQLQuestionnaireSerializer (DML).
     */
    private function tablePrefix(): string {
        return strtolower(str_replace(" ", "_", str_replace("_DICT", "", $this->dictionary->getName())));
    }

    /**
     * Community layer: prefixed table name, quoted for the target platform.
     */
    private function qt(string $suffix): string {
        return $this->conn->getDatabasePlatform()->quoteIdentifier($this->tablePrefix() . '_' . $suffix);
    }

    /**
     * Community layer: identifier quoted for the target platform.
     */
    private function qi(string $identifier): string {
        return $this->conn->getDatabasePlatform()->quoteIdentifier($identifier);
    }

    /**
     * Community layer: make sure the per-dictionary cspro_jobs table carries an
     * `error_message` column, so a failed breakout can record why it failed.
     *
     * Idempotent and non-blocking: a deployment created before this column
     * existed gets it added on the next run, and a failure to migrate never
     * stops the breakout itself.
     */
    private function ensureJobsErrorColumn(): void {
        try {
            $dictionaryLabel = strtolower(str_replace(" ", "_", str_replace("_DICT", "", $this->dictionary->getName())));
            $tableName = $dictionaryLabel . '_cspro_jobs';

            // getSchemaManager(): kept for consistency with the rest of this
            // class (cf. cleanDictionarySchema) and valid on DBAL 3.x.
            $schemaManager = $this->conn->getSchemaManager();

            // Fast path (the normal case: already migrated) reads ONLY the
            // columns (one query) instead of listTableDetails (columns + FKs +
            // indexes). This runs on every worker run, on every thread, so keep
            // it as light as possible.
            try {
                $existingColumns = $schemaManager->listTableColumns($tableName);
            } catch (\Throwable $notFound) {
                // Table not created yet: the schema definition already includes
                // the column, nothing to migrate.
                return;
            }
            foreach ($existingColumns as $col) {
                if (strtolower($col->getName()) === 'error_message') {
                    return; // already there: idempotent, no further DBAL calls.
                }
            }

            // Column missing (rare, once per deployment). Load the full table as
            // fromTable for getAlterTableSQL: avoids a DBAL deprecation and
            // produces more reliable platform-specific DDL.
            $fromTable = $schemaManager->listTableDetails($tableName);

            $platform = $this->conn->getDatabasePlatform();
            $tableDiff = new \Doctrine\DBAL\Schema\TableDiff(
                $tableName,
                [
                    'error_message' => new \Doctrine\DBAL\Schema\Column(
                        'error_message',
                        \Doctrine\DBAL\Types\Type::getType('text'),
                        ['notnull' => false, 'default' => null]
                    ),
                ],
                [], [], [], [], [],
                $fromTable
            );
            foreach ($platform->getAlterTableSQL($tableDiff) as $sql) {
                $this->conn->executeStatement($sql);
            }
            $this->logger->info("Added error_message column to $tableName (breakout failure tracking migration).");
        } catch (\Exception $e) {
            // Non-blocking: the breakout must run even if this migration fails.
            $this->logger->warning(
                "Could not ensure error_message column on cspro_jobs for Dictionary: " . $this->dictionaryName,
                ["context" => (string) $e]
            );
        }
    }

    /**
     * Community layer: record a breakout job as FAILED with a readable message,
     * so the UI can show the cause without anyone reading server logs.
     *
     * Best effort by design — never let failure bookkeeping mask the real error.
     */
    public function markJobFailed($jobId, string $errorMessage): bool {
        // Guard: initialize() can throw BEFORE $this->dictionary / $this->conn
        // are set (unknown dictionary, invalid connection parameters). Without
        // this guard, null->getName() would raise an \Error caught neither by
        // the catch below nor by the worker -> fatal. Degrade cleanly instead:
        // the failure is still logged, just not persisted.
        if (empty($jobId) || $this->dictionary === null || $this->conn === null) {
            return false;
        }
        try {
            $dictionaryLabel = strtolower(str_replace(" ", "_", str_replace("_DICT", "", $this->dictionary->getName())));
            $platform = $this->conn->getDatabasePlatform();
            $qi = fn(string $id): string => $platform->quoteIdentifier($id);

            $stm = 'UPDATE ' . $qi($dictionaryLabel . '_cspro_jobs')
                 . ' SET ' . $qi('status') . ' = :status, ' . $qi('error_message') . ' = :errorMessage'
                 . ' WHERE ' . $qi('id') . ' = :id';
            $this->conn->executeStatement($stm, [
                'status'       => self::JOB_STATUS_FAILED,
                'errorMessage' => $errorMessage,
                'id'           => $jobId,
            ]);
            return true;
        } catch (\Throwable $e) {
            // \Throwable, not just \Exception: never let failure bookkeeping
            // raise a fatal that would hide the real error.
            $this->logger->warning(
                "Could not persist FAILED status for JobID $jobId, Dictionary: " . $this->dictionaryName,
                ["context" => (string) $e]
            );
            return false;
        }
    }

}
