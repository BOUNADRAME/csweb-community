---
layout: default
title: Migration Breakout Sélectif
---

# Guide de Migration : Breakout Sélectif par Dictionnaire

> **Documentation professionnelle des transformations pour CSWeb 8 Community Platform**

**Auteur :** Assietou Diagne (Transformations originales) - Documentation par Bouna DRAME
**Date :** 14 Mars 2026
**Version CSWeb :** 8.0+
**Base de données :** PostgreSQL (principal), MySQL (supporté)

---

## 📋 Table des Matières

1. [Introduction](#introduction)
2. [Vue d'Ensemble](#vue-densemble)
3. [Fichiers Modifiés](#fichiers-modifiés)
4. [Transformations Détaillées](#transformations-détaillées)
   - [A. DictionarySchemaHelper.php](#a-dictionaryschemahelperphp)
   - [B. MySQLQuestionnaireSerializer.php](#b-mysqlquestionnaireserializerphp)
   - [C. MySQLDictionarySchemaGenerator.php](#c-mysqldictionaryschemageneratorphp)
   - [D. Nouvelle Console Command](#d-nouvelle-console-command)
5. [Pattern de Transformation](#pattern-de-transformation)
6. [Migration SQL](#migration-sql)
7. [Testing](#testing)
8. [FAQ](#faq)

---

## Introduction

Ce document présente les transformations nécessaires pour migrer CSWeb 8 vanilla d'un système de **breakout global** (tous les dictionnaires dans un seul schéma) vers un système de **breakout sélectif par dictionnaire** (chaque dictionnaire obtient son propre ensemble de tables avec préfixe de label).

### Objectifs

✅ **Isolation des dictionnaires** : Chaque dictionnaire a ses propres tables
✅ **Multi-tenancy** : Plusieurs dictionnaires dans la même base de données
✅ **Compatibilité PostgreSQL** : Support natif du driver PDO_PGSQL
✅ **Traçabilité** : Identification claire des tables par label de dictionnaire
✅ **Performance** : Multi-threading par dictionnaire

### Contexte

Ces transformations ont été réalisées par **Assietou Diagne** pour le projet KAIROS (ANSD, Sénégal) et sont adaptées ici pour être appliquées à toute installation CSWeb 8 téléchargée depuis https://csprousers.org/downloads/.

---

## Vue d'Ensemble

### Architecture AVANT

```
Base de données destinataire (MySQL)
├── DICT_cases
├── DICT_level_1
├── DICT_level_2
├── DICT_record_001
├── DICT_record_002
└── ... (toutes les tables pour TOUS les dictionnaires)
```

**Problème :** Impossible de faire du breakout sélectif. Un seul dictionnaire à la fois.

### Architecture APRÈS

```
Base de données destinataire (PostgreSQL/MySQL)
├── survey_cases
├── survey_level_1
├── survey_level_2
├── survey_record_001
├── census_cases
├── census_level_1
├── census_level_2
└── ... (tables isolées par label de dictionnaire)

Exemple du projet Kairos (ANSD, Sénégal):
├── kairos_cases
├── kairos_level_1
├── kairos_level_2
└── kairos_record_001
```

**Avantage :** Breakout simultané de plusieurs dictionnaires avec isolation complète.

---

## Fichiers Modifiés

### Fichiers PHP Principaux

| Fichier | Chemin | Type de Modification |
|---------|--------|---------------------|
| **DictionarySchemaHelper.php** | `src/AppBundle/CSPro/DictionarySchemaHelper.php` | ⚠️ Modifications majeures |
| **MySQLQuestionnaireSerializer.php** | `src/AppBundle/CSPro/MySQLQuestionnaireSerializer.php` | ⚠️ Modifications majeures |
| **MySQLDictionarySchemaGenerator.php** | `src/AppBundle/CSPro/MySQLDictionarySchemaGenerator.php` | ✏️ Modifications mineures |
| **CSWebProcessRunnerByDict.php** | `src/AppBundle/Command/CSWebProcessRunnerByDict.php` | ✨ Nouveau fichier |

### Fichiers SQL

| Fichier | Description |
|---------|-------------|
| `migration_schema.sql` | ALTER TABLE pour retirer contrainte schema_name |

---

## Transformations Détaillées

## A. DictionarySchemaHelper.php

**Chemin :** `src/AppBundle/CSPro/DictionarySchemaHelper.php`

### A.1 - cleanDictionarySchema()

**Objectif :** Supprimer uniquement les tables du dictionnaire spécifique (pas toutes les tables).

#### ❌ AVANT (Global - supprime TOUTES les tables)

```php
private function cleanDictionarySchema() {
    try {
        $tables = $this->conn->getSchemaManager()->listTables();
        if ((is_countable($tables) ? count($tables) : 0) > 0) {
            $this->conn->prepare("SET FOREIGN_KEY_CHECKS = 0;")->execute();

            foreach ($tables as $table) {
                $sql = 'DROP TABLE ' .
                MySQLDictionarySchemaGenerator::quoteString($table->getName());
                $this->conn->prepare($sql)->execute();
            }
            $this->conn->prepare("SET FOREIGN_KEY_CHECKS = 1;")->execute();
        }
    } catch (\Exception $e) {
        $strMsg = "Failed deleting tables from database: " . $this->connectionParams['dbname'] . " while processing Dictionary: " . $this->dictionaryName;
        $this->logger->error($strMsg, ["context" => (string) $e]);
        throw $e;
    }
}
```

#### ✅ APRÈS (Sélectif - supprime uniquement les tables du dictionnaire)

```php
private function cleanDictionarySchema() {
    try {
        $tables = $this->conn->getSchemaManager()->listTables();
        if (count($tables) > 0) {
            // Extraire le label du dictionnaire (ex: "SURVEY_DICT" -> "survey")
            $dictionaryLabel = str_replace(" ", "_", str_replace("_DICT", "", $this->dictionary->getName()));
            $mystring = strtolower($dictionaryLabel)."_";

            foreach ($tables as $table) {
                // Supprimer uniquement les tables avec le préfixe du dictionnaire
                if(substr($table->getName(), 0, strlen($mystring)) === $mystring){
                    $sql = 'DROP TABLE "' . $table->getName() .'" CASCADE';
                    $this->conn->prepare($sql)->execute();
                }
            }
        }
    } catch (\Exception $e) {
        $strMsg = "Failed deleting tables from database: " . $this->connectionParams['dbname'] . " while processing Dictionary: " . $this->dictionaryName;
        $this->logger->error($strMsg, array("context" => (string) $e));
        throw $e;
    }
}
```

**🔑 Changements clés :**
- ✅ Extraction du label : `str_replace("_DICT", "", $this->dictionary->getName())`
- ✅ Filtre par préfixe : `substr($table->getName(), 0, strlen($mystring)) === $mystring`
- ✅ DROP CASCADE pour PostgreSQL (gestion des clés étrangères)
- ❌ Retrait de `SET FOREIGN_KEY_CHECKS` (spécifique MySQL)

---

### A.2 - generateRecordTable()

**Objectif :** Utiliser le label du dictionnaire pour nommer les tables de records.

#### ❌ AVANT

```php
private function generateRecordTable(Record $record, $parentLevel) {
    $recordTable = $this->schema->createTable('DICT_record_' . $record->getRecordName());
    // ... reste du code
}
```

#### ✅ APRÈS

```php
private function generateRecordTable(Record $record, $parentLevel) {
    // Utilisation du label pour le nom de la table
    $recordTable = $this->schema->createTable($this->nomSchema . '_record_' . $record->getRecordName());
    // ... reste du code
}
```

**🔑 Changement :** `'DICT_record_'` → `$this->nomSchema . '_record_'`

---

### A.3 - generateLevelIdsTable()

**Objectif :** Utiliser le label pour les tables de niveaux.

#### ❌ AVANT

```php
private function generateLevelIdsTable(Level $level, $parentLevel) {
    $levelTable = $this->schema->createTable('DICT_level_' . ($level->getLevelNumber() + 1));
    // ... reste du code
}
```

#### ✅ APRÈS

```php
private function generateLevelIdsTable(Level $level, $parentLevel) {
    $levelTable = $this->schema->createTable($this->nomSchema . '_level_' . ($level->getLevelNumber() + 1));
    // ... reste du code
}
```

**🔑 Changement :** `'DICT_level_'` → `$this->nomSchema . '_level_'`

---

### A.4 - createDefaultTables()

**Objectif :** Tables par défaut (cases, geography) avec label.

#### ❌ AVANT

```php
private function createDefaultTables() {
    $casesTable = $this->schema->createTable('DICT_cases');
    // ... configuration de la table cases

    $geographyTable = $this->schema->createTable('DICT_geography');
    // ... configuration de la table geography
}
```

#### ✅ APRÈS

```php
private function createDefaultTables() {
    $casesTable = $this->schema->createTable($this->nomSchema . '_cases');
    // ... configuration de la table cases

    $geographyTable = $this->schema->createTable($this->nomSchema . '_geography');
    // ... configuration de la table geography
}
```

**🔑 Changement :** `'DICT_cases'` → `$this->nomSchema . '_cases'`

---

### A.5 - generateDictionary()

**Objectif :** Initialiser `$this->nomSchema` avec le label du dictionnaire.

#### ❌ AVANT

```php
public function generateDictionary(Dictionary $dictionary, $processCasesOptions) {
    DictionarySchemaHelper::updateProcessCasesOptions($dictionary, $processCasesOptions);
    $this->schema = new Schema();
    $this->createDefaultTables();
    // ... reste du code
}
```

#### ✅ APRÈS

```php
public function generateDictionary(Dictionary $dictionary, $processCasesOptions) {
    // Récupérer le label du dictionnaire pour la synchronisation des données
    $this->nomSchema = str_replace(" ", "_", str_replace("_DICT", "", $dictionary->getName()));

    DictionarySchemaHelper::updateProcessCasesOptions($dictionary, $processCasesOptions);
    $this->schema = new Schema();
    $this->createDefaultTables();
    // ... reste du code
}
```

**🔑 Ajout :** Initialisation de `$this->nomSchema` en début de méthode.

---

### A.6 - isValidSchema()

**Objectif :** Validation du schéma avec les noms de tables basés sur le label.

#### ❌ AVANT

```php
public function isValidSchema() {
    try {
        $tables = $this->conn->getSchemaManager()->listTableNames();
        return in_array('DICT_cases', $tables) &&
               in_array('DICT_level_1', $tables);
    } catch (\Exception $e) {
        $this->logger->error('Failed checking for valid schema', ["context" => (string) $e]);
        return false;
    }
}
```

#### ✅ APRÈS

```php
public function isValidSchema() {
    try {
        $dictionaryLabel = str_replace(" ", "_", str_replace("_DICT", "", $this->dictionary->getName()));
        $tables = $this->conn->getSchemaManager()->listTableNames();

        return in_array(strtolower($dictionaryLabel).'_cases', $tables) &&
               in_array(strtolower($dictionaryLabel).'_level_1', $tables);
    } catch (\Exception $e) {
        $this->logger->error('Failed checking for valid schema', array("context" => (string) $e));
        return false;
    }
}
```

**🔑 Changement :** Vérification avec le nom de table préfixé par le label.

---

### A.7 - resetInProcessJobs()

**Objectif :** Réinitialiser les jobs avec les bons noms de tables.

#### ❌ AVANT

```php
public function resetInProcessJobs() {
    try {
        $this->conn->executeUpdate("UPDATE DICT_cases SET processing_status = 0 WHERE processing_status = 1");
    } catch (\Exception $e) {
        $this->logger->error('Failed resetting in process jobs', ["context" => (string) $e]);
        throw $e;
    }
}
```

#### ✅ APRÈS

```php
public function resetInProcessJobs() {
    try {
        $dictionaryLabel = str_replace(" ", "_", str_replace("_DICT", "", $this->dictionary->getName()));
        $this->conn->executeUpdate("UPDATE " . strtolower($dictionaryLabel) . "_cases SET processing_status = 0 WHERE processing_status = 1");
    } catch (\Exception $e) {
        $this->logger->error('Failed resetting in process jobs', array("context" => (string) $e));
        throw $e;
    }
}
```

**🔑 Changement :** `UPDATE DICT_cases` → `UPDATE {label}_cases`

---

### A.8 - processNextJob()

**Objectif :** Traiter le prochain job avec les bons noms de tables.

#### ❌ AVANT

```php
public function processNextJob($maxRecords) {
    $stmt = "SELECT guid FROM `DICT_cases` WHERE deleted = 0 AND (processing_status IS NULL OR processing_status = 0) LIMIT " . $maxRecords;
    // ... reste du code
}
```

#### ✅ APRÈS

```php
public function processNextJob($maxRecords) {
    $dictionaryLabel = str_replace(" ", "_", str_replace("_DICT", "", $this->dictionary->getName()));
    $stmt = "SELECT guid FROM " . strtolower($dictionaryLabel) . "_cases WHERE deleted = 0 AND (processing_status IS NULL OR processing_status = 0) LIMIT " . $maxRecords;
    // ... reste du code
}
```

**🔑 Changement :** Requête SQL avec nom de table dynamique.

---

### A.9 - createJob()

**Objectif :** Créer un job dans `cspro_jobs` avec le label du dictionnaire.

#### ❌ AVANT

```php
public static function createJob($pdo, $dictionaryName, $targetSchemaName, $targetHostName, $dbUserName, $dbPassword) {
    $sql = "INSERT INTO `cspro_jobs` (dictionary_name, target_schema_name, target_host_name, db_username, db_password) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dictionaryName, $targetSchemaName, $targetHostName, $dbUserName, $dbPassword]);
}
```

#### ✅ APRÈS

```php
public static function createJob($pdo, $dictionaryName, $targetSchemaName, $targetHostName, $dbUserName, $dbPassword, $labelDictionnaire = null) {
    $sql = "INSERT INTO `cspro_jobs` (dictionary_name, target_schema_name, target_host_name, db_username, db_password, label_dictionnaire) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dictionaryName, $targetSchemaName, $targetHostName, $dbUserName, $dbPassword, $labelDictionnaire]);
}
```

**🔑 Ajout :** Nouveau paramètre `$labelDictionnaire` pour traçabilité.

---

### A.10 - getDataCounts()

**Objectif :** Récupérer les statistiques avec les bons noms de tables.

#### ❌ AVANT

```php
public function getDataCounts(&$dataSettings) {
    foreach ($dataSettings as &$dataSetting) {
        $stm = "SELECT count(*) FROM `" . $dataSetting['name'] . "` WHERE `deleted`=0";
        $caseCount = (int) $this->pdo->fetchValue($stm);

        $statement = $conn->executeQuery('SELECT count(*) FROM cases where deleted=0');
        $processedCases = $statement->fetchOne();
    }
}
```

#### ✅ APRÈS

```php
public function getDataCounts(&$dataSettings) {
    foreach ($dataSettings as &$dataSetting) {
        $name_dict = str_replace(" ", "_", str_replace("_DICT", "", $dataSetting['name']))."_";

        $stm = "SELECT count(*) FROM `" . $dataSetting['name'] . "` WHERE `deleted`=0";
        $caseCount = (int) $this->pdo->fetchValue($stm);

        // PostgreSQL avec préfixe de label
        $statement = $conn->executeQuery('SELECT count(*) FROM '.$name_dict.'cases where deleted=0');
        $processedCases = $statement->fetchOne();
    }
}
```

**🔑 Changement :** Requête sur `{label}_cases` au lieu de `cases`.

---

## B. MySQLQuestionnaireSerializer.php

**Chemin :** `src/AppBundle/CSPro/MySQLQuestionnaireSerializer.php`

### B.1 - __construct()

**Objectif :** Initialiser `$this->labelDictionnaire` au chargement du serializer.

#### ❌ AVANT

```php
public function __construct(
    private Dictionary $dict,
    private $jobId,
    private PdoHelper $sourcePdo,
    private Connection $targetConnection,
    private LoggerInterface $logger
) {
    $this->casesMap = [];
}
```

#### ✅ APRÈS

```php
public function __construct(
    private Dictionary $dict,
    private $jobId,
    private PdoHelper $sourcePdo,
    private Connection $targetConnection,
    private LoggerInterface $logger
) {
    $this->casesMap = [];

    // Récupération du label du dictionnaire pour mettre à jour le préfixe des tables de report
    $this->labelDictionnaire = str_replace(" ", "_", str_replace("_DICT", "", $dict->getName()));
}
```

**🔑 Ajout :** Initialisation de `$this->labelDictionnaire`.

---

### B.2 - serializeQuestionnaires()

**Objectif :** Utiliser le label pour les requêtes d'insertion.

#### ❌ AVANT

```php
public function serializeQuestionnaires($caseIds) {
    $markAsProcessedStmt = $this->targetConnection->prepare('UPDATE DICT_cases SET processing_status=2 WHERE guid=?');
    // ... reste du code
}
```

#### ✅ APRÈS

```php
public function serializeQuestionnaires($caseIds) {
    $markAsProcessedStmt = $this->targetConnection->prepare('UPDATE ' . strtolower($this->labelDictionnaire) . '_cases SET processing_status=2 WHERE guid=?');
    // ... reste du code
}
```

**🔑 Changement :** `DICT_cases` → `{label}_cases`

---

### B.3 - getJobInformation()

**Objectif :** Récupérer les infos du job avec le label.

#### ❌ AVANT

```php
public function getJobInformation() {
    $stm = "SELECT count(*) FROM `DICT_cases` WHERE `deleted`=0";
    // ... reste du code
}
```

#### ✅ APRÈS

```php
public function getJobInformation() {
    $stm = "SELECT count(*) FROM `" . strtolower($this->labelDictionnaire) . "_cases` WHERE `deleted`=0";
    // ... reste du code
}
```

**🔑 Changement :** Requête avec nom de table dynamique.

---

### B.4 - deleteQuestionnaires()

**Objectif :** Supprimer les questionnaires avec le bon nom de table.

#### ❌ AVANT

```php
public function deleteQuestionnaires($caseIds) {
    $deleteStmt = $this->targetConnection->prepare('UPDATE DICT_cases SET deleted=1 WHERE guid=?');
    // ... reste du code
}
```

#### ✅ APRÈS

```php
public function deleteQuestionnaires($caseIds) {
    $deleteStmt = $this->targetConnection->prepare('UPDATE ' . strtolower($this->labelDictionnaire) . '_cases SET deleted=1 WHERE guid=?');
    // ... reste du code
}
```

**🔑 Changement :** `DICT_cases` → `{label}_cases`

---

### B.5 - generateLevelInsertStatement()

**Objectif :** Générer les INSERT avec les bons noms de tables de niveaux.

#### ❌ AVANT

```php
private function generateLevelInsertStatement($level) {
    $tableName = 'DICT_level_' . ($level->getLevelNumber() + 1);
    // ... reste du code
}
```

#### ✅ APRÈS

```php
private function generateLevelInsertStatement($level) {
    $tableName = strtolower($this->labelDictionnaire) . '_level_' . ($level->getLevelNumber() + 1);
    // ... reste du code
}
```

**🔑 Changement :** `DICT_level_` → `{label}_level_`

---

### B.6 - generateRecordInsertStatement()

**Objectif :** Générer les INSERT pour les records avec label.

#### ❌ AVANT

```php
private function generateRecordInsertStatement(Record $record) {
    $tableName = 'DICT_record_' . $record->getRecordName();
    // ... reste du code
}
```

#### ✅ APRÈS

```php
private function generateRecordInsertStatement(Record $record) {
    $tableName = strtolower($this->labelDictionnaire) . '_record_' . $record->getRecordName();
    // ... reste du code
}
```

**🔑 Changement :** `DICT_record_` → `{label}_record_`

---

### B.7 - serializeCases()

**Objectif :** Sérialiser les cases avec le bon nom de table.

#### ❌ AVANT

```php
private function serializeCases($questionnaire) {
    $casesStmt = $this->targetConnection->prepare("INSERT INTO DICT_cases ...");
    // ... reste du code
}
```

#### ✅ APRÈS

```php
private function serializeCases($questionnaire) {
    $casesStmt = $this->targetConnection->prepare("INSERT INTO " . strtolower($this->labelDictionnaire) . "_cases ...");
    // ... reste du code
}
```

**🔑 Changement :** `DICT_cases` → `{label}_cases`

---

### B.8 - serializeNotes()

**Objectif :** Sérialiser les notes avec le bon nom de table.

#### ❌ AVANT

```php
private function serializeNotes($caseId, $notes) {
    $notesStmt = $this->targetConnection->prepare("INSERT INTO DICT_notes ...");
    // ... reste du code
}
```

#### ✅ APRÈS

```php
private function serializeNotes($caseId, $notes) {
    $notesStmt = $this->targetConnection->prepare("INSERT INTO " . strtolower($this->labelDictionnaire) . "_notes ...");
    // ... reste du code
}
```

**🔑 Changement :** `DICT_notes` → `{label}_notes`

---

### B.9 - getCaseIdsMap()

**Objectif :** Récupérer la map des case IDs avec le bon nom de table.

#### ❌ AVANT

```php
private function getCaseIdsMap() {
    try {
        $stm = 'SELECT "level-1-id" as id, "case-id" as guid FROM `level-1` WHERE `case-id` in (';
        // ... reste du code
    }
}
```

#### ✅ APRÈS

```php
private function getCaseIdsMap() {
    try {
        $stm = 'SELECT "level-1-id" as id, "case-id" as guid FROM "'.strtolower($this->labelDictionnaire).'_level-1" WHERE "case-id" in (';
        // ... reste du code
    }
}
```

**🔑 Changement :** `level-1` → `{label}_level-1`

---

## C. MySQLDictionarySchemaGenerator.php

**Chemin :** `src/AppBundle/CSPro/MySQLDictionarySchemaGenerator.php`

### C.1 - Ajout de la propriété $nomSchema

#### ✅ AJOUT

```php
class MySQLDictionarySchemaGenerator {
    private Schema $schema;
    private LoggerInterface $logger;
    private $nomSchema; // ← NOUVELLE PROPRIÉTÉ

    // ... reste du code
}
```

**🔑 Ajout :** Propriété privée pour stocker le label du dictionnaire.

---

### C.2 - generateDictionary()

**Objectif :** Initialiser `$this->nomSchema` avant génération.

#### ✅ APRÈS

```php
public function generateDictionary(Dictionary $dictionary, $processCasesOptions) {
    // Récupérer le label du dictionnaire pour la synchronisation des données
    $this->nomSchema = str_replace(" ", "_", str_replace("_DICT", "", $dictionary->getName()));

    DictionarySchemaHelper::updateProcessCasesOptions($dictionary, $processCasesOptions);
    $this->schema = new Schema();
    $this->createDefaultTables();

    $parentLevel = null;
    for ($iLevel = 0; $iLevel < count($dictionary->getLevels()); $iLevel++) {
        $level = $dictionary->getLevels()[$iLevel];
        $level->setLevelNumber($iLevel);
        $this->generateLevel($level, $parentLevel);
        $parentLevel = $dictionary->getLevels()[$iLevel];
    }

    return $this->schema;
}
```

**🔑 Ajout :** Initialisation de `$this->nomSchema` en première ligne.

---

## D. Nouvelle Console Command

**Chemin :** `src/AppBundle/Command/CSWebProcessRunnerByDict.php`

### D.1 - Fichier Complet

#### ✨ NOUVEAU FICHIER

```php
<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use AppBundle\Service\PdoHelper;
use AppBundle\CSPro\Dictionary\MySQLDictionarySchemaGenerator;
use AppBundle\CSPro\Dictionary\PdoHelper as DictPdoHelper;
use AppBundle\CSPro\DictionarySchemaHelper;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Schema;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;
use AppBundle\CSPro\DBConfigSettings;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Description of CSWebProcessRunner - based on
 * https://stackoverflow.com/questions/54127418/backend-multi-threading-in-php-7-symfony4
 *
 * @author savy
 */
class CSWebProcessRunnerByDict extends Command {

    protected static $defaultName = 'csweb:process-cases-by-dict';
    use LockableTrait;

    public const MAX_TIME_LIMIT = 3600; //seconds

    private $startTime;
    private $phpBinaryPath;
    private $dictionaryMap;
    private $maxCasesPerChunk;
    private $output;

    //TODO: eventually use DBAL instead of PDO for all the service operations.
    public function __construct(private PdoHelper $pdo, private KernelInterface $kernel, private LoggerInterface $logger) {
        parent::__construct();
        $this->dictionaryMap = [];
    }

    protected function configure() {
        //configuration is set to running max three threads per dictionary
        //with each thread processing a max of 500 cases
        $this->setDescription('CSWeb blob breakout processing into multiple threads')
            ->addOption('threads', 't', InputOption::VALUE_REQUIRED, 'Number of threads to run at once per dictionary', 3)
            ->addOption('maxCasesPerChunk', 'c', InputOption::VALUE_REQUIRED, 'Number of cases to process per chunk', 1000)
            ->addArgument('dictionnaires', InputArgument::IS_ARRAY, 'Tableau de dictionnaires cspro');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $dictionnaires = $input->getArgument('dictionnaires');
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');
            $this->logger->info('The command is already running in another process.');
            return 0;
        }

        $this->logger->info('Started process at ' . date("c"));
        $this->startTime = microtime(true);
        $this->output = $output;
        $this->maxCasesPerChunk = $input->getOption('maxCasesPerChunk');
        $threadsPerDictionary = $input->getOption('threads');
        $output->writeln('Running blob breakout process.');

        if (extension_loaded('pcntl')) {
            $stop = function () {
                $output = null;
                $this->logger->error('Abort process issued');
                $output->writeln('Abort process issued.');
                $output->writeln('Stopping blob breakout process.');
                throw new RuntimeException('Abort process Issued');
            };
            pcntl_signal(SIGTERM, $stop);
            pcntl_signal(SIGINT, $stop);
            pcntl_async_signals(true);
        }

        //generate schema for each dictionary that is to be processed if it does not exists
        $this->createDictionarySchemas($dictionnaires);
        $stopProcess = $this->hasProcessTimeExpired();

        do {
            try {
                //set stop process flag if process time expires and no threads are running currently
                $stopProcess = count($this->dictionaryMap) == 0 || ($this->hasProcessTimeExpired() && $this->canExitProcess());

                foreach (array_keys($this->dictionaryMap) as $dictionaryName) {
                    $dictionaryInfo = &$this->dictionaryMap[$dictionaryName];

                    if ($dictionaryInfo->processFlag == false &&
                        (is_countable($dictionaryInfo->processes) ? count($dictionaryInfo->processes) : 0) == 0) {
                        //no jobs available and no running threads for this dictionary. Remove dictionary from processing
                        unset($this->dictionaryMap[$dictionaryName]);
                        $this->logger->info("No jobs available to process for dictionary: " . $dictionaryName);
                    }

                    //create new threads if duration is within process expiry time.
                    while ((is_countable($dictionaryInfo->processes) ? count($dictionaryInfo->processes) : 0) < $threadsPerDictionary && !$this->hasProcessTimeExpired() && $dictionaryInfo->processFlag) {

                        $output->writeln('Processing dictionary: ' . $dictionaryName . ' - Running threads ' . (is_countable($dictionaryInfo->processes) ? count($dictionaryInfo->processes) : 0));
                        $this->logger->debug('CSWeb Process Runner creating a new blob breakout thread');

                        $output->writeln('creating a new blob breakout thread');
                        $process = $this->createProcess($dictionaryName);
                        if ($process) {
                            $process->setTimeout(self::MAX_TIME_LIMIT);
                            $process->setIdleTimeout(self::MAX_TIME_LIMIT);
                            $process->start();
                            $dictionaryInfo->processes[] = $process;
                        }
                    }

                    //filters array and returns running processes for the current dictionary
                    $dictionaryInfo->processes = array_filter($dictionaryInfo->processes, fn(Process $p) => $p->isRunning());
                }

                //For use to debug
                /* for ($j = 0; $j < count($dictionaryInfo->processes); $j++) {
                    $dictionaryInfo->processes[$j]->wait(function ($type, $buffer) {
                        echo 'OUT > ' . $type;
                        if (Process::ERR === $type) {
                            echo 'ERR > ' . $buffer;
                        } else {
                            echo 'OUT > ' . $buffer;
                        }
                    });

                    $this->output->writeln("removing process count is " . count($dictionaryInfo->processes));
                    array_splice($dictionaryInfo->processes, $j, 1);
                    $this->output->writeln("after removal process count is " . count($dictionaryInfo->processes));
                } */

                sleep(1);

            } catch (RuntimeException) {
                try {
                    $this->output->writeln("Killing process");
                    defined('SIGKILL') || define('SIGKILL', 9);
                    //kill the running threads
                    foreach (array_keys($this->dictionaryMap) as $dictionaryName) {
                        $dictionaryInfo = &$this->dictionaryMap[$dictionaryName];
                        array_map(function (Process $p) {
                            $p->signal(SIGKILL);
                        }, $dictionaryInfo->processes);
                    }
                } catch (\Throwable) {
                }
                break;
            }
        } while (!$stopProcess);

        $this->release();
        $this->logger->info('Stopping process at ' . date("c"));
        return 0;
    }

    private function createProcess($dictName) {
        // var_dump('HERE'); die;
        if (!isset($this->dictionaryMap[$dictName])) {
            $this->logger->error("Invalid dictionary Map. Dictionary information not set for dictionary " . $dictName);
            return null;
        }

        $dictionaryInfo = &$this->dictionaryMap[$dictName];
        $dictionarySchemaHelper = $dictionaryInfo->schemaHelper;
        $jobId = $dictionarySchemaHelper->processNextJob($this->maxCasesPerChunk);

        $this->output->writeln('Creating process for dictionary ' . $dictName . ' jobID: ' . $jobId);
        $this->logger->debug('Creating process for dictionary ' . $dictName . ' jobID: ' . $jobId);

        if (!$this->phpBinaryPath) {
            $this->phpBinaryPath = (new PhpExecutableFinder())->find();
        }

        if ($jobId) {
            $cmd = [
                $this->phpBinaryPath,
                '-f',
                realpath($this->kernel->getProjectDir() . '/bin/console'),
                '--',
                'csweb:blob-breakout-worker',
                '-a',
                $this->kernel->getEnvironment(),
                '-d',
                $dictName,
                '-j',
                $jobId,
            ];

            $this->output->writeln('Processing for dictionary ' . $dictName . ' jobID: ' . $jobId);
            $this->logger->debug('Processing for dictionary ' . $dictName . ' jobID: ' . $jobId);

            return new Process($cmd);
        }

        $this->output->writeln('No jobs available to run for dictionary: ' . $dictName);

        //set process flag to false to stop creating threads for this dictionary
        $dictionaryInfo->processFlag = false;
        return null;
    }

    private function createDictionarySchemas($tabDict) {

        $listDictName = "'DICT'";

        foreach($tabDict as $dict){
            $listDictName = $listDictName .",'".$dict."'";
        }

        //do exception handling
        $stm = 'SELECT id, dictionary_name as dictName FROM `cspro_dictionaries` JOIN `cspro_dictionaries_schema` ON dictionary_id = cspro_dictionaries.id WHERE dictionary_name IN ('.$listDictName.')';

        $result = $this->pdo->fetchAll($stm);

        if (count($result) > 0) {
            $this->dictionaryMap = [];

            foreach ($result as $row) {
                $this->logger->info('Updating schema tables for Dictionary: ' . $row['dictName']);

                $dictionarySchemaHelper = new DictionarySchemaHelper($row['dictName'], $this->pdo, $this->logger);
                $dictionarySchemaHelper->initialize(true);
                $dictionarySchemaHelper->resetInProcessJobs();

                //set the dictionary information
                $dictionaryInfo = new \stdClass;
                $dictionaryInfo->schemaHelper = $dictionarySchemaHelper;
                $dictionaryInfo->processFlag = true;
                $dictionaryInfo->processes = [];

                $this->dictionaryMap[$row['dictName']] = $dictionaryInfo;
            }
        }
    }

    private function canExitProcess(): bool {
        $flag = true;
        foreach (array_keys($this->dictionaryMap) as $dictionaryName) {
            $dictionaryInfo = $this->dictionaryMap[$dictionaryName];
            $processes = $dictionaryInfo->processes;
            if (isset($processes) && (is_countable($processes) ? count($processes) : 0) > 0) { //if threads are running return false;
                $flag = false;
                break;
            }
        }
        return $flag;
    }

    private function hasProcessTimeExpired() {
        $duration = round((microtime(true) - $this->startTime));
        return $duration > self::MAX_TIME_LIMIT;
    }
}
```

**🔑 Points clés :**

1. **Multi-threading par dictionnaire** : Gestion de 3 threads par dictionnaire
2. **Tableau de dictionnaires** : Argument `dictionnaires` pour traiter plusieurs dictionnaires
3. **Gestion de processus** : Création/monitoring de processus blob breakout
4. **Timeout** : MAX_TIME_LIMIT = 3600 secondes (1 heure)
5. **Command name** : `csweb:process-cases-by-dict`

---

## Pattern de Transformation

### Règle Générale

**Pour toute référence à une table CSPro :**

```php
// ❌ AVANT (hardcodé)
'DICT_cases'
'DICT_level_1'
'DICT_record_001'

// ✅ APRÈS (dynamique avec label)
strtolower($this->labelDictionnaire) . '_cases'
strtolower($this->labelDictionnaire) . '_level_1'
strtolower($this->labelDictionnaire) . '_record_001'
```

### Extraction du Label

```php
// Depuis le nom du dictionnaire (ex: "SURVEY_DICT")
$label = str_replace(" ", "_", str_replace("_DICT", "", $dictionaryName));
// Résultat: "SURVEY"

// En minuscules pour PostgreSQL
$prefix = strtolower($label) . "_";
// Résultat: "survey_"

// Exemple du projet Kairos (ANSD):
// "KAIROS_DICT" -> "kairos_"
```

### Initialisation dans les Classes

```php
// Dans MySQLDictionarySchemaGenerator
public function generateDictionary(Dictionary $dictionary, $processCasesOptions) {
    $this->nomSchema = str_replace(" ", "_", str_replace("_DICT", "", $dictionary->getName()));
    // ... reste du code
}

// Dans MySQLQuestionnaireSerializer
public function __construct(...) {
    $this->labelDictionnaire = str_replace(" ", "_", str_replace("_DICT", "", $dict->getName()));
}
```

---

## Migration SQL

### Retrait de la Contrainte schema_name

La table `cspro_dictionaries_schema` avait une clé unique sur `schema_name` qui empêchait plusieurs dictionnaires de pointer vers le même schéma PostgreSQL.

**Migration SQL :**

```sql
-- Générique (remplacer csweb_metadata par le nom de votre base)
ALTER TABLE csweb_metadata.cspro_dictionaries_schema DROP KEY schema_name;

-- Exemple du projet Kairos (ANSD):
ALTER TABLE csweb_kairos.cspro_dictionaries_schema DROP KEY schema_name;
```

**Impact :**
- ✅ Permet à plusieurs dictionnaires de coexister dans la même base de données
- ✅ Chaque dictionnaire a ses tables isolées par préfixe de label
- ✅ Pas besoin de créer une base par dictionnaire

---

## Testing

### Test 1 : Vérification de l'Isolation

**Objectif :** Vérifier que deux dictionnaires créent des tables séparées.

```bash
# Console command générique
php bin/console csweb:process-cases-by-dict dictionnaires=SURVEY_DICT,CENSUS_DICT
```

**Résultat attendu dans PostgreSQL :**

```sql
-- Tables du dictionnaire SURVEY
survey_cases
survey_level_1
survey_level_2
survey_record_001

-- Tables du dictionnaire CENSUS
census_cases
census_level_1
census_level_2
census_record_001

-- Exemple du projet Kairos (ANSD):
-- Tables: kairos_cases, kairos_level_1, kairos_level_2, kairos_record_001
```

### Test 2 : Multi-Threading

**Objectif :** Vérifier que 3 threads tournent par dictionnaire.

```bash
# Avec logs de debug
php bin/console csweb:process-cases-by-dict dictionnaires=SURVEY_DICT --threads=3 -vvv
```

**Logs attendus :**

```
[INFO] Updating schema tables for Dictionary: SURVEY_DICT
[DEBUG] CSWeb Process Runner creating a new blob breakout thread
[DEBUG] Creating process for dictionary SURVEY_DICT jobID: 1
[DEBUG] Creating process for dictionary SURVEY_DICT jobID: 2
[DEBUG] Creating process for dictionary SURVEY_DICT jobID: 3
```

### Test 3 : Comptage des Cas

**Objectif :** Vérifier que `getDataCounts()` retourne les bons chiffres.

```php
// Depuis un contrôleur ou test unitaire
$dataSettings = [
    ['name' => 'SURVEY_DICT', 'targetSchemaName' => 'survey_schema', ...]
];

$counts = $helper->getDataCounts($dataSettings);

// Vérifications
$this->assertEquals(1500, $counts[0]['totalCases']); // Source MySQL
$this->assertEquals(1500, $counts[0]['processedCases']); // Destination PostgreSQL

// Exemple du projet Kairos (ANSD):
// $dataSettings = [['name' => 'KAIROS_DICT', 'targetSchemaName' => 'kairos_schema']]
```

---

## FAQ

### Q1 : Pourquoi `strtolower()` sur les noms de tables ?

**R :** PostgreSQL convertit automatiquement les identifiants non-quotés en minuscules. Utiliser `strtolower()` assure la cohérence entre les requêtes.

```sql
-- PostgreSQL traite ces deux requêtes de manière identique :
SELECT * FROM SURVEY_cases;
SELECT * FROM survey_cases;

-- Exemple du projet Kairos (ANSD):
-- SELECT * FROM KAIROS_cases; = SELECT * FROM kairos_cases;
```

### Q2 : Peut-on mélanger MySQL et PostgreSQL ?

**R :** Oui, dans cette architecture :
- **Source (CSWeb)** : MySQL (base d'origine avec les données CSPro)
- **Destination (Breakout)** : PostgreSQL (base de report avec tables par label)

Les deux bases communiquent via Doctrine DBAL.

### Q3 : Que se passe-t-il si deux dictionnaires ont le même label ?

**R :** **CONFLIT !** Les tables se chevaucheront. Il faut garantir l'unicité des labels :

```php
// Solution : Validation au moment de la création du job
public static function createJob($pdo, $dictionaryName, ..., $labelDictionnaire) {
    // Vérifier que le label n'existe pas déjà
    $existing = $pdo->fetchValue("SELECT COUNT(*) FROM cspro_jobs WHERE label_dictionnaire = ?", [$labelDictionnaire]);
    if ($existing > 0) {
        throw new \Exception("Label dictionnaire '$labelDictionnaire' already exists!");
    }
    // ... reste du code
}
```

### Q4 : Comment migrer une installation CSWeb existante ?

**Étapes :**

1. **Backup complet** de la base MySQL source
2. **Créer base PostgreSQL** destinataire
3. **Appliquer la migration SQL** (retrait contrainte schema_name)
4. **Modifier les 3 fichiers PHP** comme documenté
5. **Créer le nouveau command** `CSWebProcessRunnerByDict.php`
6. **Tester sur 1 dictionnaire** avec peu de cas
7. **Valider les tables créées** dans PostgreSQL
8. **Lancer le breakout complet** avec tous les dictionnaires

### Q5 : Quels modules PHP sont requis ?

```bash
# Vérifier les modules installés
php -m

# Modules REQUIS pour PostgreSQL
- pdo_pgsql
- pgsql

# Modules REQUIS pour MySQL
- pdo_mysql
- mysqli

# Modules Symfony
- mbstring
- xml
- intl
```

**Installation sur Ubuntu :**

```bash
sudo apt-get install php-pgsql php-mysql php-mbstring php-xml php-intl
```

### Q6 : Comment débugger les erreurs de breakout ?

**Logs à vérifier :**

```bash
# Logs Symfony
tail -f var/logs/dev.log

# Logs de la console command
php bin/console csweb:process-cases-by-dict dictionnaires=KAIROS_DICT -vvv

# Logs PostgreSQL
sudo tail -f /var/log/postgresql/postgresql-14-main.log
```

**Erreurs courantes :**

| Erreur | Cause | Solution |
|--------|-------|----------|
| `SQLSTATE[42P01]: Undefined table` | Table avec mauvais nom | Vérifier `$this->labelDictionnaire` |
| `SQLSTATE[23505]: Unique violation` | Tentative d'insertion duplicate | Vérifier les clés primaires (guid) |
| `Connection refused` | PostgreSQL pas démarré | `sudo service postgresql start` |
| `Class not found` | Autoload pas à jour | `composer dump-autoload` |

---

## Conclusion

Cette migration transforme CSWeb 8 d'un système **monolithique** (1 dictionnaire = 1 base) vers un système **multi-tenant** (N dictionnaires = 1 base avec isolation par label).

### Avantages

✅ **Économie de ressources** : 1 seule base PostgreSQL pour tous les dictionnaires
✅ **Isolation garantie** : Chaque dictionnaire a ses propres tables
✅ **Performance** : Multi-threading par dictionnaire (3 threads × 500 cas)
✅ **Flexibilité** : Ajout/retrait de dictionnaires sans impact sur les autres
✅ **Traçabilité** : Label visible dans les noms de tables

### Prochaines Étapes

1. **Interface d'administration** : Gérer les dictionnaires via UI web
2. **Choix de base de données** : Sélectionner PostgreSQL/MySQL/SQL Server via config
3. **Détection automatique** : Vérifier modules PHP requis au démarrage
4. **Documentation utilisateur** : Guide pas-à-pas pour non-développeurs
5. **Docker Compose** : `docker-compose up -d` pour lancement automatique

---

## Support

**Questions sur cette migration ?**

- 📧 bounafode@gmail.com
- 💬 GitHub Discussions : https://github.com/BOUNADRAME/pg_csweb8_latest_2026/discussions
- 🐛 GitHub Issues : https://github.com/BOUNADRAME/pg_csweb8_latest_2026/issues

---

**Made with ❤️ by Assietou Diagne (Code) & Bouna DRAME (Documentation)**

**CSWeb Community Platform - Démocratiser CSWeb pour l'Afrique**

---

## Annexes

### A. Checklist de Migration

```markdown
- [ ] Backup complet base MySQL source
- [ ] Créer base PostgreSQL destinataire
- [ ] Installer modules PHP requis (pdo_pgsql)
- [ ] Appliquer migration SQL (DROP KEY schema_name)
- [ ] Modifier DictionarySchemaHelper.php (10 méthodes)
- [ ] Modifier MySQLQuestionnaireSerializer.php (9 méthodes)
- [ ] Modifier MySQLDictionarySchemaGenerator.php (propriété + generateDictionary)
- [ ] Créer CSWebProcessRunnerByDict.php
- [ ] Tester avec 1 dictionnaire (10 cas)
- [ ] Valider tables créées dans PostgreSQL
- [ ] Lancer breakout complet
- [ ] Documenter dans MIGRATION.md
```

### B. Glossaire

| Terme | Définition |
|-------|------------|
| **Breakout** | Processus de transformation des données CSPro (blob) vers tables relationnelles |
| **Label dictionnaire** | Préfixe unique identifiant un dictionnaire (ex: `kairos_`) |
| **Multi-tenancy** | Architecture permettant plusieurs dictionnaires dans une base |
| **PDO_PGSQL** | Driver PHP pour se connecter à PostgreSQL |
| **Schema** | Ensemble de tables appartenant à un dictionnaire |
| **Blob breakout worker** | Processus en arrière-plan qui traite les cas CSPro |

### C. Références

- **CSPro Users Forum** : https://www.csprousers.org/forum/
- **CSWeb Downloads** : https://csprousers.org/downloads/
- **Doctrine DBAL** : https://www.doctrine-project.org/projects/dbal.html
- **Symfony Console** : https://symfony.com/doc/current/console.html
- **PostgreSQL Documentation** : https://www.postgresql.org/docs/

---

**Version :** 1.0.0
**Dernière mise à jour :** 14 Mars 2026
**Statut :** ✅ Production-ready
