<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\CSPro\Data;

use App\CSPro\Dictionary\Dictionary;
use App\CSPro\Dictionary\Level;
use App\CSPro\Dictionary\Record;
use App\CSPro\Dictionary\Item;
use Doctrine\DBAL\Schema\Schema;
use Psr\Log\LoggerInterface;
use App\Service\PdoHelper;
use Doctrine\DBAL\Connection;
use App\CSPro\Dictionary\MySQLDictionarySchemaGenerator;
use App\CSPro\DictionarySchemaHelper;
use Exception;

/**
 * Description of MySQLQuestionnaireSerializer
 *
 * @author savy
 */
class MySQLQuestionnaireSerializer {

    private $casesMap;  //target db connection
    private $casesIdMap;
    private $job;
    private $labelDictionnaire;
    private $targetPlatform; //Doctrine DBAL platform of the target breakout DB (MySQL / PostgreSQL / SQL Server)

    public function __construct(private Dictionary $dict, private $jobId, private PdoHelper $sourcePdo, private Connection $targetConnection, private LoggerInterface $logger) {
        $this->casesMap = [];
        // Récupération du label du dictionnaire pour construire le préfixe des tables de breakout.
        // Plusieurs dictionnaires peuvent partager un même schéma cible (breakout sélectif) :
        // les tables sont donc préfixées <dict>_cases, <dict>_notes, <dict>_level-1, <dict>_cspro_jobs...
        // Même construction que MySQLDictionarySchemaGenerator::$nomSchama, pour que DDL et DML
        // désignent exactement les mêmes tables.
        $this->labelDictionnaire = str_replace(" ", "_", str_replace("_DICT", "", $dict->getName()));
        // Le SQL de breakout doit fonctionner pour MySQL, PostgreSQL et SQL Server.
        // On ne code JAMAIS le caractère de quoting en dur (backtick MySQL vs
        // double-quote ANSI) : on délègue à la plateforme DBAL de la base cible,
        // qui applique la bonne convention. C'est aussi celle utilisée à la
        // création du schéma, donc les identifiants DDL et DML restent cohérents.
        $this->targetPlatform = $targetConnection->getDatabasePlatform();
    }

    /**
     * Quote an identifier (table or column name) using the target database
     * platform. Portable across MySQL (`id`), PostgreSQL / SQL Server ("id").
     */
    private function qi(string $identifier): string {
        return $this->targetPlatform->quoteIdentifier($identifier);
    }

    /**
     * Nom de table cible préfixé par le dictionnaire, en minuscules
     * (important sur MySQL sensible à la casse sous Linux), non quoté.
     */
    private function tableName(string $suffix): string {
        return strtolower($this->labelDictionnaire) . '_' . $suffix;
    }

    /**
     * Nom de table cible préfixé ET quoté pour la plateforme cible.
     */
    private function qt(string $suffix): string {
        return $this->qi($this->tableName($suffix));
    }

    public function serializeQuestionnaries($processCasesOptions) {
        $bind = [];
        DictionarySchemaHelper::updateProcessCasesOptions($this->dict, $processCasesOptions, $this->logger);
        //        ini_set('memory_limit', '16G'); //increase memory if php memory limit is hit 
        $this->getJobInformation();
        $this->getQuestionnarieListToSerilaize();
        if (count($this->casesMap) == 0) {
            $this->logger->warning("No cases available to serialize for jobId: " . $this->jobId);
            return;
        }
        $strMsg = "Serializing " . count($this->casesMap) . " cases for dictionary : " . $this->dict->getName();
        $this->logger->info($strMsg);
        //delete questionnaires in a separate transaction to avoid deadlock issues
        try {
            $this->deleteQuestionnaires();
        } catch (\Exception $e) {
            $strMsg = '[SourceDB: ' . $this->sourcePdo->getDsn() . ' TargetDB: ' . $this->targetConnection->getDatabase();
            $strMsg .= ' Dictionary: ' . $this->dict->getName();
            $strMsg .= '] Failed serializing cases';
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw new \Exception($strMsg, 0, $e);
        }

        //begin transaction 
        $this->targetConnection->beginTransaction();
        try {
            $caseCount = $this->serializeCases();
            $caseCount = $this->serializeQuestionnaireLevel();
            $this->serializeQuestionnaireRecords();
            $this->serializeNotes();

            //update job
            $jobId = $this->jobId;
            $stm = 'UPDATE ' . $this->qt('cspro_jobs') . ' SET ' . $this->qi('status') . '= :status, ' . $this->qi('cases_processed') . ' = :totalCases WHERE ' . $this->qi('id') . ' = :jobId';
            $bind['status'] = DictionarySchemaHelper::JOB_STATUS_COMPLETE;
            $bind['jobId'] = $this->jobId;
            $bind['totalCases'] = $caseCount;
            $this->targetConnection->executeUpdate($stm, $bind);
            //commit 
            $this->targetConnection->commit();
        } catch (\Exception $e) {
            $strMsg = '[SourceDB: ' . $this->sourcePdo->getDsn() . ' TargetDB: ' . $this->targetConnection->getDatabase();
            $strMsg .= ' Dictionary: ' . $this->dict->getName();
            $strMsg .= '] Failed serializing cases';
            $this->logger->error($strMsg, ["context" => (string) $e]);
            $this->targetConnection->rollBack();
            throw new \Exception($strMsg, 0, $e);
        }
    }

    /**
     * Nouveauté vanilla 8.1 : les colonnes created_time / modified_time ne sont
     * insérées dans la table des cases que si elles existent réellement dans le
     * schéma cible (schéma créé par une version antérieure = colonnes absentes).
     *
     * Adaptation Community : la table est préfixée par le dictionnaire
     * (<dict>_cases) puisque plusieurs dictionnaires partagent le même schéma.
     *
     * Adaptation Community (portabilité) : le vanilla interroge
     * information_schema avec `table_schema` = nom de la base, sémantique propre
     * à MySQL — sur PostgreSQL `table_schema` désigne le schéma (public), sur
     * SQL Server il vaut 'dbo', si bien que la détection renvoyait toujours
     * false et que la nouveauté 8.1 restait inactive sur ces deux moteurs.
     * On passe par le SchemaManager DBAL, qui résout information_schema /
     * pg_catalog / sys.columns selon la plateforme : même comportement sur
     * MySQL, et fonctionnalité enfin active sur PostgreSQL et SQL Server.
     *
     * Le repli reste sûr : toute erreur d'introspection (table pas encore
     * créée, droits insuffisants) retombe sur false, c.-à-d. l'INSERT sans
     * created_time / modified_time.
     */
    function includeCasesCreatedModifiedTime() {
        $casesTableName = $this->tableName('cases');
        try {
            $columns = $this->targetConnection->getSchemaManager()->listTableColumns($casesTableName);
            foreach ($columns as $column) {
                if (strtolower($column->getName()) === 'created_time') {
                    return true;
                }
            }
        } catch (\Throwable $ex) {
            // Table absente ou introspection refusée : on n'insère simplement
            // pas les deux colonnes.
        }
        return false;
    }

    public function getJobInformation() {
        try {
            $jobColumns = ['id', 'start_caseid', 'start_revision', 'end_caseid', 'end_revision', 'cases_to_process'];
            $quotedJobColumns = implode(', ', array_map(fn($c) => $this->qi($c), $jobColumns));
            $stm = 'SELECT ' . $quotedJobColumns . ' FROM ' . $this->qt('cspro_jobs')
                    . ' WHERE ' . $this->qi('id') . ' = ' . (int) $this->jobId;
            $result = $this->targetConnection->fetchAllAssociative($stm);
            unset($this->job);
            if ($result) {
                $this->job = $result [0];
            }
        } catch (\Exception $e) {
            $strMsg = '[SourceDB: ' . $this->sourcePdo->getDsn() . ' TargetDB: ' . $this->targetConnection->getDatabase();
            $strMsg .= ' Dictionary: ' . $this->dict->getName();
            $strMsg .= "] Failed getting job information jobID: " . $this->jobId;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw new \Exception($strMsg, 0, $e);
        }
    }

    //get list of questionnaires to process for the current job
    public function getQuestionnarieListToSerilaize() {
        //From the source get the list of cases
        try {
            // Select all the cases sent by the client that exist on the server
            $stm = 'SELECT  `id`, LCASE(CONCAT_WS("-", LEFT(HEX(uuid), 8), MID(HEX(uuid), 9,4), MID(HEX(uuid), 13,4), MID(HEX(uuid), 17,4), RIGHT(HEX(uuid), 12))) as uuid, questionnaire, `revision`,
                    `key`, `label`, `deleted`, `verified`, `modified_time`, `created_time`, `partial_save_mode`
			FROM ' . $this->dict->getName() . ' WHERE (`id` >= :startCaseId AND `revision` =  :startRevision) ';

            $stm .= " UNION " . 'SELECT  `id`, LCASE(CONCAT_WS("-", LEFT(HEX(uuid), 8), MID(HEX(uuid), 9,4), MID(HEX(uuid), 13,4), MID(HEX(uuid), 17,4), RIGHT(HEX(uuid), 12))) as uuid, questionnaire, `revision`,
                    `key`, `label`, `deleted`, `verified`, `modified_time`, `created_time`, `partial_save_mode`
			FROM ' . $this->dict->getName() . ' WHERE (`revision` >  :startRevision AND `revision` <= :endRevision) ';

            $stm .= ' ORDER BY  `revision`, `id`  LIMIT :limit; ';
            $stmt = $this->sourcePdo->prepare($stm);

            $stmt->bindParam(':limit', $this->job['cases_to_process'], \PDO::PARAM_INT);
            $stmt->bindParam(':startCaseId', $this->job['start_caseid']);
            $stmt->bindParam(':startRevision', $this->job['start_revision']);
            $stmt->bindParam(':endRevision', $this->job['end_revision']);

//            $this->logger->debug($stmt->queryString);
            $stmt->execute();
            $result = $stmt->fetchAll();

            $this->casesMap = [];
            foreach ($result as &$row) {
                $row['questionnaire'] = gzuncompress(substr($row['questionnaire'], 4));
                $this->casesMap[$row ['uuid']] = $row;
            }
        } catch (\Exception $e) {
            $strMsg = '[SourceDB: ' . $this->sourcePdo->getDsn() . ' TargetDB: ' . $this->targetConnection->getDatabase();
            $strMsg .= ' Dictionary: ' . $this->dict->getName();
            $strMsg .= '] Failed getting cases to process for dictionary ';
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw new \Exception($strMsg, 0, $e);
        }
    }

    //cascade delete exisiting questionnaires before breaking out JSON
    public function deleteQuestionnaires() {
        //delete all the questionnaires that match 
        $caseList = array_keys($this->casesMap);
        $strCaseList = "'" . implode("','", $caseList) . "'";

        $this->targetConnection->beginTransaction();
        try {
            //delete existing cases
            $stm = 'DELETE FROM ' . $this->qt('cases') . ' WHERE ' . $this->qi('id') . ' in ( ' . $strCaseList . ')';
            $count = $this->targetConnection->executeUpdate($stm);

            //delete notes for these cases
            $stm = 'DELETE FROM ' . $this->qt('notes') . ' WHERE ' . $this->qi('case_id') . ' in ( ' . $strCaseList . ')';
            $this->targetConnection->executeUpdate($stm);

            //cascade delete cases from break out tables
            $stm = 'DELETE FROM ' . $this->qt('level-1') . ' WHERE ' . $this->qi('case-id') . ' in ( ' . $strCaseList . ')';
            $count = $this->targetConnection->executeUpdate($stm);
            $this->logger->debug("Deleted $count cases");

            $this->targetConnection->commit();
        } catch (\Exception $e) {
            $strMsg = '[SourceDB: ' . $this->sourcePdo->getDsn() . ' TargetDB: ' . $this->targetConnection->getDatabase();
            $strMsg .= ' Dictionary: ' . $this->dict->getName();
            $strMsg .= '] Failed deleting cases';
            $this->logger->error($strMsg, ["context" => (string) $e]);
            $this->targetConnection->rollBack();
            throw new \Exception($strMsg, 0, $e);
        }
    }

    private function generateLevelInsertStatement(&$nameTypeMap): string {
        $stm = 'INSERT INTO ' . $this->qt('level-1') . ' (';
        //TODO: fix for multiple levels
        $iLevel = 0;
        $level = $this->dict->getLevels()[$iLevel];

        for ($iItem = 0; $iItem < (is_countable($level->getIdItems()) ? count($level->getIdItems()) : 0); $iItem++) {
            $this->getRecordItemNameType($level->getIdItems()[$iItem], $nameTypeMap);
        }
        $keys = array_keys($nameTypeMap);
        $quotedItemNames = [];
        foreach ($keys as $key) {
            $quotedItemNames[] = $this->qi($key);
        }
        $itemList = implode(",", $quotedItemNames);
        $itemList = $this->qi('case-id') . ',' . $itemList;

        $stm .= $itemList . ') VALUES ';
        return $stm;
    }

    private function generateRecordInsertStatement(Record $record, &$nameTypeMap): string {
        $recordName = $this->qt(strtolower($record->getName()));
        $stm = "INSERT INTO $recordName (";

        $this->getRecordItemsNameType($record, $nameTypeMap);
        $keys = array_keys($nameTypeMap);
        $quotedItemNames = [];
        foreach ($keys as $key) {
            $quotedItemNames[] = $this->qi($key);
        }

        $itemList = implode(",", $quotedItemNames);

        $parentLevelName = "level-" . (string) ($record->getLevel()->getLevelNumber() + 1);
        $parentId = $this->qi($parentLevelName . "-id");

        if ($record->getMaxRecords() > 1) {
            $itemList = $parentId . ', ' . $this->qi('occ') . ', ' . $itemList;
        } else {
            $itemList = $parentId . ',' . $itemList;
        }
        $itemList = rtrim($itemList, ",");
        $stm .= $itemList . ") VALUES ";

        return $stm;
    }

    private function getRecordItemsNameType(Record $record, &$nameTypeMap) {
        $parentItem = null;
        for ($iItem = 0; $iItem < (is_countable($record->getItems()) ? count($record->getItems()) : 0); $iItem++) {
            $item = $record->getItems()[$iItem];
            if ($item->getItemType() === "Item") {
                $parentItem = $item;
                $item->setParentItem(null);
            } else {
                $item->setParentItem($parentItem);
            }
            if ($item->isIncludedInBlobBreakOut()) {
                $this->getRecordItemNameType($item, $nameTypeMap);
            }
        }
    }

    public function getRecordItemNameType(Item $item, &$nameTypeMap) {

        $itemName = strtolower($item->getName());
        $itemType = MySQLDictionarySchemaGenerator::generateColumnType($item);
        $itemOccurrences = $item->getItemSubitemOccurs();

        if ($itemOccurrences == 1) {
            $nameTypeMap[$itemName] = $itemType;
        } else {
            for ($occurrence = 1; $occurrence <= $itemOccurrences; $occurrence++) {
                $itemNameWithOccurrence = $itemName . '(' . $occurrence . ')';
                $nameTypeMap[$itemNameWithOccurrence] = $itemType;
            }
        }
    }

    public function serializeQuestionnaireLevel(): int {
        $caseList = array_keys($this->casesMap);
        $nameTypeMap = [];
        $stm = $this->generateLevelInsertStatement($nameTypeMap);
        $idItemNames = array_keys($nameTypeMap);
        $idItemNames = array_map('strtoupper', $idItemNames);
        $values = [];
        $singlePlaceholder = '(' . implode(', ', array_fill(0, count($idItemNames) + 1, '?')) . ')';
        // (?, ?), ... , (?, ?)
        $placeholders = implode(', ', array_fill(0, count($this->casesMap), $singlePlaceholder));

        $this->logger->debug('serializeQuestionnaireLevel: processing ' . count($this->casesMap) . ' cases to insert');
        $level = $this->dict->getLevels()[0];
        $levelName = strtoupper($level->getName());
        foreach ($caseList as $case) {
            $caseJsonArray = $this->casesMap[$case];
            $values[] = $case; //case-id
            foreach ($idItemNames as $idItem) {
                if (isset($caseJsonArray[$levelName][$idItem]["code"])) {
                    $values[] = $caseJsonArray[$levelName][$idItem]["code"];
                } else {
                    $values[] = null;
                }
            }
        }

        $stm .= $placeholders;
        try {
            $count = $this->targetConnection->executeUpdate($stm, $values);
            $this->logger->debug("inserted  $count rows into case level");
        } catch (\Exception $e) {
            $strMsg = '[SourceDB: ' . $this->sourcePdo->getDsn() . ' TargetDB: ' . $this->targetConnection->getDatabase();
            $strMsg .= ' Dictionary: ' . $this->dict->getName();
            $strMsg .= "] Failed writing cases level information to database for  jobID: " . $this->jobId;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw new \Exception($strMsg, 0, $e);
        }
        return $count;
    }

    public function serializeCases(): int {
        $caseList = array_keys($this->casesMap);
        // Colonnes de la table <dict>_cases. On les quote via la plateforme cible :
        // 'key' est un mot réservé MySQL (mais pas PostgreSQL), donc la liste
        // non quotée cassait sur MySQL. Le quoting plateforme règle ça pour les
        // 3 SGBD sans traiter de colonne au cas par cas.
        if ($this->includeCasesCreatedModifiedTime()) {
            $caseColumns = ['id', 'key', 'label', 'last_modified_revision', 'deleted', 'verified', 'modified_time', 'created_time', 'partial_save_mode'];
            $itemNames = ["uuid", "key", "label", "revision", "deleted", "verified", "modified_time", "created_time", "partial_save_mode"];
        } else {
            $caseColumns = ['id', 'key', 'label', 'last_modified_revision', 'deleted', 'verified', 'partial_save_mode'];
            $itemNames = ["uuid", "key", "label", "revision", "deleted", "verified", "partial_save_mode"];
        }
        $quotedCaseColumns = implode(', ', array_map(fn($c) => $this->qi($c), $caseColumns));
        $stm = 'INSERT INTO ' . $this->qt('cases') . ' (' . $quotedCaseColumns . ') VALUES ';
        $values = [];
        $singlePlaceholder = '(' . implode(', ', array_fill(0, count($itemNames), '?')) . ')';
        // (?, ?), ... , (?, ?)
        $placeholders = implode(', ', array_fill(0, count($this->casesMap), $singlePlaceholder));

        $this->logger->debug('Inserting into cases table: processing ' . count($this->casesMap) . ' cases to insert');
        foreach ($caseList as $case) {
            $caseRow = $this->casesMap[$case];
            foreach ($itemNames as $itemName) {
                if (isset($caseRow[$itemName])) {
                    $values[] = $caseRow[$itemName];
                } else {
                    $values[] = null;
                }
            }
            //once the case is processed change the key in the map to point to json decoded questionnaire for the 
            //rest of the tables to be broken out
            $this->casesMap[$case] = json_decode($caseRow['questionnaire'], true);
            if (json_last_error() != JSON_ERROR_NONE) {
                $strMsg = '[SourceDB: ' . $this->sourcePdo->getDsn() . ' TargetDB: ' . $this->targetConnection->getDatabase();
                $strMsg .= ' Dictionary: ' . $this->dict->getName() . "] Failed writing cases to database for  jobID: " . $this->jobId;
                $strQuestionnaire = ' Case: ' . $case . ' Questionnaire: ' . $caseRow['questionnaire'];
                $this->logger->error($strMsg . $strQuestionnaire . " Error decoding json questionnaire. " . json_last_error_msg());
                throw new \Exception($strMsg);
            }
        }

        $stm .= $placeholders;
        try {
            $count = $this->targetConnection->executeUpdate($stm, $values);
            $this->logger->debug("inserted  $count rows into cases table");
        } catch (\Exception $e) {
            $strMsg = '[SourceDB: ' . $this->sourcePdo->getDsn() . ' TargetDB: ' . $this->targetConnection->getDatabase();
            $strMsg .= ' Dictionary: ' . $this->dict->getName();
            $strMsg .= "] Failed writing cases to database for  jobID: " . $this->jobId;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw new \Exception($strMsg, 0, $e);
        }
        return $count;
    }

    public function serializeNotes(): int {
        $caseList = array_keys($this->casesMap);
        $result = [];
        foreach ($caseList as $case) {
            $case = $this->casesMap[$case];
            if (isset($case["notes"])) {
                foreach ($case["notes"] as $note) {
                    $row['case_id'] = $case['uuid'];
                    $row['field_name'] = $note['name'] ?? "";
                    $default = $this->dict->getName() ===  $row['field_name'] ? 0 : 1; //case note has no occurrences set
                    $row['level_key'] = $note['levelKey'] ?? null;
                    $row['record_occurrence'] = $note['occurrences']['record'] ?? $default;
                    $row['item_occurrence'] = $note['occurrences']['item'] ?? $default;
                    $row['subitem_occurrence'] = $note['occurrences']['subitem'] ?? 0;
                    $row['operator_id'] = $note['operatorId'] ?? null;

                    $rawTime = $note['modifiedTime'] ?? null;
                    $timestamp = $rawTime ? strtotime($rawTime) : false;
                    $row['modified_time'] = $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;

                    $row['content'] = $note['text'] ?? "";
                    $result[] = $row;
                }
            }
        }
        try {
            if ((is_countable($result) ? count($result) : 0) == 0)
                return 0;

            //add the notes for these cases to  the notes table
            $noteColumns = ['case_id', 'field_name', 'level_key', 'record_occurrence', 'item_occurrence', 'subitem_occurrence', 'content', 'operator_id', 'modified_time'];
            $quotedNoteColumns = implode(', ', array_map(fn($c) => $this->qi($c), $noteColumns));
            $stm = 'INSERT INTO ' . $this->qt('notes') . ' (' . $quotedNoteColumns . ') VALUES ';
            $itemNames = ["case_id", "field_name", "level_key", "record_occurrence", "item_occurrence", "subitem_occurrence", "content", "operator_id", "modified_time"];
            $values = [];
            $singlePlaceholder = '(' . implode(', ', array_fill(0, count($itemNames), '?')) . ')';
            // (?, ?), ... , (?, ?)
            $placeholders = implode(', ', array_fill(0, is_countable($result) ? count($result) : 0, $singlePlaceholder));

            $this->logger->debug('Inserting into notes table');
            foreach ($result as $row) {
                foreach ($itemNames as $itemName) {
                    $values[] = $row[$itemName] ?? null;
                }
            }

            $stm .= $placeholders;
            $count = $this->targetConnection->executeUpdate($stm, $values);
            $this->logger->debug("inserted  $count notes");
        } catch (\Exception $e) {
            $strMsg = '[SourceDB: ' . $this->sourcePdo->getDsn() . ' TargetDB: ' . $this->targetConnection->getDatabase();
            $strMsg .= ' Dictionary: ' . $this->dict->getName();
            $strMsg .= "] Failed writing case notes to database for  jobID: " . $this->jobId;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw new \Exception($strMsg, 0, $e);
        }
        return $count;
    }

    private function getCaseIdsMap() {
        try {
            // Select all the cases sent by the client that exist on the server
            $stm = 'SELECT  ' . $this->qi('level-1-id') . ' as id, ' . $this->qi('case-id') . ' as uuid FROM ' . $this->qt('level-1')
                    . ' WHERE ' . $this->qi('case-id') . ' in (';
            $strOrderBy = ' ORDER BY  id';

            $strCaseList = "'" . implode("','", array_keys($this->casesMap)) . "'";
            $stm = $stm . $strCaseList . ")" . $strOrderBy;
            $result = $this->targetConnection->fetchAllAssociative($stm);

            $this->casesIdMap = [];
            foreach ($result as $row) {
                $this->casesIdMap[$row ['uuid']] = $row['id'];
            }
        } catch (\Exception $e) {
            $strMsg = '[SourceDB: ' . $this->sourcePdo->getDsn() . ' TargetDB: ' . $this->targetConnection->getDatabase();
            $strMsg .= ' Dictionary: ' . $this->dict->getName();
            $strMsg .= '] Failed getting cases to process for dictionary';
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw new \Exception($strMsg, 0, $e);
        }
    }

    public function serializeQuestionnaireRecords() {
        $iLevel = 0;
        $level = $this->dict->getLevels()[$iLevel];
        $this->getCaseIdsMap();
        try {
            for ($iRecord = 0; $iRecord < (is_countable($level->getRecords()) ? count($level->getRecords()) : 0); $iRecord++) {
                $record = $level->getRecords()[$iRecord];
                $record->setLevel($level);
                if ($record->isIncludedInBlobBreakOut()) {
                    $this->logger->debug('serializing record ' . $record->getName());
                    $this->serializeRecord($record);
                }
            }
        } catch (\Exception $e) {
            $strMsg = '[SourceDB: ' . $this->sourcePdo->getDsn() . ' TargetDB: ' . $this->targetConnection->getDatabase();
            $strMsg .= ' Dictionary: ' . $this->dict->getName();
            $strMsg .= '] Failed writing case records to database for jobID: ' . $this->jobId;
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw new \Exception($strMsg, 0, $e);
        }
    }

    public function fillItemValues(Item $item, Record $record, $curRecord, &$values) {
        $occurs = $item->getItemSubitemOccurs();
        $itemName = strtoupper($item->getName());
        $isNumeric = $item->isNumeric();

        if ($occurs > 1) {
            $itemOccValues = array_fill(0, $occurs, null);
            if (isset($curRecord[$itemName])) {
                $itemValuesArray = $curRecord[$itemName];
                for ($iItemValue = 0; $iItemValue < (is_countable($itemValuesArray) ? count($itemValuesArray) : 0); $iItemValue++) {
                    $itemOccValues[$iItemValue] = $itemValuesArray[$iItemValue]["code"]??null;
                    if ($isNumeric && isset($itemOccValues[$iItemValue])) {
                        if (is_numeric($itemOccValues[$iItemValue]) === FALSE) {
                            $this->logger->warning("Record [" . $record->getName() . "] Item [$itemName] has invalid numeric value $itemValuesArray[$iItemValue]. Setting it to null");
                            $itemOccValues[$iItemValue] = null;
                        }
                    }
                }
            }
            $values = array_merge($values, $itemOccValues);
        } else {
            $insertValue = null;
            if (isset($curRecord[$itemName]["code"])) {
                $insertValue = $curRecord[$itemName]["code"];
                if ($isNumeric) {
                    if (is_numeric($insertValue) === FALSE) {
                        $this->logger->warning("Record [" . $record->getName() . "] Item [$itemName] has invalid numeric value $insertValue. Setting it to null");
                        $insertValue = null;
                    }
                }
            }
            $values[] = $insertValue;
        }
    }

    public function serializeRecord(Record $record) {
        $caseList = array_keys($this->casesMap);
        $nameTypeMap = [];
        $stm = $this->generateRecordInsertStatement($record, $nameTypeMap);
        $recordItemNames = array_keys($nameTypeMap);
        $recordItemNames = array_map('strtoupper', $recordItemNames);
        $values = [];
        //add +1 for level-1-id
        if ($record->getMaxRecords() > 1) {//to account for level id and occ 
            $singlePlaceholder = '(' . implode(', ', array_fill(0, count($recordItemNames) + 2, '?')) . ')';
        } else {//to account for level-id
            $singlePlaceholder = '(' . implode(', ', array_fill(0, count($recordItemNames) + 1, '?')) . ')';
        }
        // (?, ?), ... , (?, ?)

        $recordCount = 0;
        $level = $this->dict->getLevels()[0];
        $levelName = strtoupper($level->getName());
        //get the hashmap of caseIds and their new ids to insert into the records id as foreign key
        foreach ($caseList as $case) {
            $caseJsonArray = $this->casesMap[$case][$levelName];
            $newCaseId = $this->casesIdMap[$case];
            unset($recordList);
            if (isset($caseJsonArray[$record->getName()])) {
                $recordList = $caseJsonArray[$record->getName()];
                foreach ($recordList as $curRec) {
                    $recordCount++;
                    $values[] = $newCaseId;
                    if ($record->getMaxRecords() > 1) {
                        $values[] = $recordCount;
                    }

                    $parentItem = null;
                    for ($iItem = 0; $iItem < (is_countable($record->getItems()) ? count($record->getItems()) : 0); $iItem++) {
                        $item = $record->getItems()[$iItem];
                        if ($item->getItemType() === "Item") {
                            $parentItem = $item;
                            $item->setParentItem(null);
                        } else {
                            $item->setParentItem($parentItem);
                        }
                        if ($item->isIncludedInBlobBreakOut()) {
                            $this->fillItemValues($item, $record, $curRec, $values);
                        }
                    }
                }
            }
        }

        $placeholders = implode(', ', array_fill(0, $recordCount, $singlePlaceholder));

        $stm .= $placeholders;
        if ($recordCount == 0) {
            $this->logger->debug("No records to output " . $record->getName());
            return;
        }
        try {
            $count = $this->targetConnection->executeUpdate($stm, $values);
            $this->logger->debug("inserted  $count records");
        } catch (\Exception $e) {
            $strMsg = '[SourceDB: ' . $this->sourcePdo->getDsn() . ' TargetDB: ' . $this->targetConnection->getDatabase();
            $strMsg .= ' Dictionary: ' . $this->dict->getName();
            $strMsg .= "] Failed writing case records to database for  record: " . $record->getName();
            $this->logger->error($strMsg, ["context" => (string) $e]);
            throw new \Exception($strMsg, 0, $e);
        }
    }

}
