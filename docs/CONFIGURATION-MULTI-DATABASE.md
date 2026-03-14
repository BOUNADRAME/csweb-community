---
layout: default
title: CONFIGURATION MULTI DATABASE
---

# Configuration Multi-Database pour Breakout Sélectif

> **Guide complet de configuration pour supporter PostgreSQL, MySQL et SQL Server**

**Auteur :** Bouna DRAME
**Date :** 14 Mars 2026
**Version :** 1.0.0

---

## 📋 Table des Matières

1. [Introduction](#introduction)
2. [Architecture](#architecture)
3. [Prérequis PHP](#prérequis-php)
4. [Configuration .env](#configuration-env)
5. [Services PHP](#services-php)
6. [Utilisation](#utilisation)
7. [Console Commands](#console-commands)
8. [Exemples Pratiques](#exemples-pratiques)
9. [Troubleshooting](#troubleshooting)
10. [FAQ](#faq)

---

## Introduction

Cette couche de configuration permet de **choisir dynamiquement** le type de base de données (PostgreSQL, MySQL ou SQL Server) pour chaque dictionnaire lors du breakout.

### ⚠️ IMPORTANT : Deux Bases de Données Distinctes

CSWeb utilise **DEUX types de bases de données** avec des responsabilités différentes :

1. **MySQL Métadonnées CSWeb (FIXE - Ne pas toucher)**
   - Fichier config : `src/AppBundle/config.php`
   - Créé par : `setup.php` lors de l'installation
   - Usage : Métadonnées CSWeb, synchronisation devices, utilisateurs, OAuth
   - Tables : `cspro_dictionaries`, `cspro_users`, `cspro_files`, etc.
   - **⚠️ NE JAMAIS modifier** cette configuration

2. **PostgreSQL/MySQL/SQL Server Breakout (CONFIGURABLE - Cette doc)**
   - Fichier config : `.env`
   - Géré par : `BreakoutDatabaseConfig` service
   - Usage : Tables relationnelles de breakout, analytics
   - Tables : `{label}_cases`, `{label}_level_1`, `{label}_record_001`, etc.
   - **✅ Configurable** via cette couche multi-database

**Cette documentation concerne UNIQUEMENT la base de données de breakout (#2).**

Pour plus de détails sur la séparation des bases de données, voir [NOTES-CONFIGURATION-CSWEB.md](NOTES-CONFIGURATION-CSWEB.md).

### Objectifs

✅ **Flexibilité** : Choisir PostgreSQL, MySQL ou SQL Server par dictionnaire
✅ **Détection automatique** : Vérifier les modules PHP requis (`php -m`)
✅ **Configuration centralisée** : Tout se configure via `.env`
✅ **Adaptabilité à chaud** : Changer de base sans modifier le code
✅ **Multi-tenancy** : Plusieurs dictionnaires, plusieurs bases de données

### Fonctionnalités

- ✨ Détection automatique des drivers PHP disponibles
- ✨ Configuration par défaut avec override par dictionnaire
- ✨ Test de connexion intégré
- ✨ Console command pour vérifier les prérequis
- ✨ Support de 3 bases : PostgreSQL, MySQL, SQL Server
- ✨ Génération automatique des noms de tables avec label

---

## Architecture

### Vue d'Ensemble

```
┌─────────────────────────────────────────────────────────────┐
│                    CSWeb Application                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │  BreakoutDatabaseConfig                            │    │
│  │  - Gère la configuration des DB                    │    │
│  │  - Mapping dictionnaire → type de DB               │    │
│  │  - Génération noms de tables avec label            │    │
│  └────────────────────────────────────────────────────┘    │
│                          ↓                                  │
│  ┌────────────────────────────────────────────────────┐    │
│  │  DatabaseDriverDetector                            │    │
│  │  - Détecte les modules PHP installés               │    │
│  │  - Teste les connexions                            │    │
│  │  - Génère instructions d'installation              │    │
│  └────────────────────────────────────────────────────┘    │
│                          ↓                                  │
│  ┌──────────────┬──────────────┬─────────────────────┐    │
│  │ PostgreSQL   │    MySQL     │    SQL Server       │    │
│  │ (default)    │  (optional)  │    (optional)       │    │
│  └──────────────┴──────────────┴─────────────────────┘    │
│         ↓              ↓                  ↓                │
│  ┌──────────────┬──────────────┬─────────────────────┐    │
│  │ survey_*     │ census_*     │ health_*            │    │
│  │ tables       │ tables       │ tables              │    │
│  └──────────────┴──────────────┴─────────────────────┘    │
│                                                              │
│  Exemple projet Kairos (ANSD): kairos_* tables             │
└─────────────────────────────────────────────────────────────┘
```

### Flux de Décision

```
Dictionnaire "SURVEY_DICT" demande breakout
         ↓
BreakoutDatabaseConfig.getDatabaseConfigForDictionary("SURVEY_DICT")
         ↓
1. Vérifier mapping spécifique pour "SURVEY_DICT"
   └─ Existe ? → Utiliser le type mappé
   └─ Pas de mapping ? → Utiliser DEFAULT_BREAKOUT_DB_TYPE
         ↓
2. Récupérer config de connexion pour le type choisi
   └─ PostgreSQL ? → POSTGRES_HOST, POSTGRES_PORT, etc.
   └─ MySQL ? → MYSQL_HOST, MYSQL_PORT, etc.
   └─ SQL Server ? → SQLSERVER_HOST, SQLSERVER_PORT, etc.
         ↓
3. Générer les paramètres Doctrine DBAL
         ↓
4. Créer connexion et tables avec préfixe "survey_"

Exemple du projet Kairos (ANSD):
"KAIROS_DICT" → tables avec préfixe "kairos_"
```

---

## Prérequis PHP

### Extensions Requises par Base de Données

| Base de Données | Extensions PHP Requises |
|-----------------|------------------------|
| **PostgreSQL** | `pdo`, `pdo_pgsql`, `pgsql` |
| **MySQL** | `pdo`, `pdo_mysql`, `mysqli` |
| **SQL Server** | `pdo`, `pdo_sqlsrv`, `sqlsrv` |

### Extensions Recommandées (Toutes Bases)

- `mbstring` - Manipulation de chaînes multi-bytes
- `xml` - Support XML
- `intl` - Internationalisation
- `json` - Support JSON
- `openssl` - Cryptographie
- `zip` - Compression

### Vérification des Extensions

```bash
# Lister toutes les extensions chargées
php -m

# Vérifier une extension spécifique
php -m | grep pdo_pgsql

# Vérifier la version PHP
php -v
```

### Installation des Extensions

#### Ubuntu/Debian

```bash
# PostgreSQL
sudo apt-get update
sudo apt-get install -y php-pgsql php-pdo

# MySQL
sudo apt-get install -y php-mysql php-pdo

# SQL Server (nécessite PECL)
sudo apt-get install -y php-dev php-pear
sudo pecl install sqlsrv pdo_sqlsrv

# Extensions recommandées
sudo apt-get install -y php-mbstring php-xml php-intl php-zip

# Redémarrer Apache/PHP-FPM
sudo systemctl restart apache2
# OU
sudo systemctl restart php8.1-fpm
```

#### CentOS/RHEL

```bash
# PostgreSQL
sudo yum install -y php-pgsql php-pdo

# MySQL
sudo yum install -y php-mysqlnd php-pdo

# Extensions recommandées
sudo yum install -y php-mbstring php-xml php-intl

# Redémarrer Apache/PHP-FPM
sudo systemctl restart httpd
```

#### Alpine Linux (Docker)

```bash
# PostgreSQL
apk add php-pgsql php-pdo_pgsql

# MySQL
apk add php-mysqli php-pdo_mysql

# Extensions recommandées
apk add php-mbstring php-xml php-intl php-openssl php-zip
```

#### macOS (Homebrew)

```bash
# Installer PHP complet
brew install php

# Les extensions PDO sont généralement incluses
# Vérifier avec: php -m
```

---

## Configuration .env

### Variables de Configuration

Le fichier `.env` contient toute la configuration des bases de données.

#### PostgreSQL (Base par Défaut)

```bash
# ========================================
# POSTGRESQL (Breakout Analytics - Default)
# ========================================
POSTGRES_HOST=localhost
POSTGRES_PORT=5432
POSTGRES_DATABASE=csweb_analytics
POSTGRES_USER=csweb_analytics
POSTGRES_PASSWORD=your_secure_password_here

# PostgreSQL URL (format Doctrine)
POSTGRES_URL=postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@${POSTGRES_HOST}:${POSTGRES_PORT}/${POSTGRES_DATABASE}
```

#### MySQL (Optionnel)

```bash
# ========================================
# MYSQL (CSWeb Metadata + Optionnel Breakout)
# ========================================
MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_DATABASE=csweb_metadata
MYSQL_USER=csweb
MYSQL_PASSWORD=your_secure_password_here

# MySQL URL
DATABASE_URL=mysql://${MYSQL_USER}:${MYSQL_PASSWORD}@${MYSQL_HOST}:${MYSQL_PORT}/${MYSQL_DATABASE}
```

#### SQL Server (Optionnel)

```bash
# ========================================
# SQL SERVER (Optional - Enterprise)
# ========================================
SQLSERVER_HOST=localhost
SQLSERVER_PORT=1433
SQLSERVER_DATABASE=csweb_analytics
SQLSERVER_USER=sa
SQLSERVER_PASSWORD=YourStrong!Passw0rd
```

#### Configuration Breakout

```bash
# ========================================
# BREAKOUT CONFIGURATION
# ========================================
# Type de base de données par défaut (postgresql|mysql|sqlserver)
DEFAULT_BREAKOUT_DB_TYPE=postgresql

# Taille de batch (nombre de cas traités à la fois)
BREAKOUT_BATCH_SIZE=1000

# Durée maximale d'un job de breakout (secondes)
BREAKOUT_MAX_DURATION=600

# Auto-création des tables si elles n'existent pas
BREAKOUT_AUTO_CREATE_TABLES=true
```

### Exemple Complet

Voir le fichier `.env.example` à la racine du projet pour un exemple complet avec tous les paramètres.

---

## Services PHP

### 1. BreakoutDatabaseConfig

**Chemin :** `src/AppBundle/Service/BreakoutDatabaseConfig.php`

Service principal de configuration des bases de données.

#### Méthodes Principales

```php
// Obtenir la config pour un type de DB
$config = $breakoutDbConfig->getDatabaseConfig('postgresql');

// Obtenir la config pour un dictionnaire spécifique
$config = $breakoutDbConfig->getDatabaseConfigForDictionary('KAIROS_DICT');

// Associer un dictionnaire à un type de DB
$breakoutDbConfig->setDictionaryDatabase('SURVEY_DICT', 'postgresql');
$breakoutDbConfig->setDictionaryDatabase('CENSUS_DICT', 'mysql');

// Exemple du projet Kairos (ANSD):
// $breakoutDbConfig->setDictionaryDatabase('KAIROS_DICT', 'postgresql');

// Générer les paramètres de connexion Doctrine DBAL
$params = $breakoutDbConfig->generateConnectionParams('KAIROS_DICT');

// Obtenir le nom de schéma pour un dictionnaire
$schema = $breakoutDbConfig->getSchemaNameForDictionary('SURVEY_DICT');
// Résultat: "survey"

// Obtenir le préfixe de table
$prefix = $breakoutDbConfig->getTablePrefixForDictionary('SURVEY_DICT');
// Résultat: "survey_"

// Construire le nom complet d'une table
$tableName = $breakoutDbConfig->getFullTableName('SURVEY_DICT', 'cases');
// Résultat: "survey_cases"

// Exemple du projet Kairos (ANSD):
// getSchemaNameForDictionary('KAIROS_DICT') → "kairos"
// getFullTableName('KAIROS_DICT', 'cases') → "kairos_cases"
```

#### Exemple d'Utilisation

```php
use AppBundle\Service\BreakoutDatabaseConfig;

class MyService {
    public function __construct(private BreakoutDatabaseConfig $dbConfig) {}

    public function performBreakout(string $dictionaryName) {
        // Obtenir la config de connexion
        $connectionParams = $this->dbConfig->generateConnectionParams($dictionaryName);

        // Créer connexion Doctrine DBAL
        $conn = DriverManager::getConnection($connectionParams);

        // Obtenir le nom de la table cases
        $casesTable = $this->dbConfig->getFullTableName($dictionaryName, 'cases');
        // Pour SURVEY_DICT: retourne "survey_cases"
        // Pour CENSUS_DICT: retourne "census_cases"

        // Exécuter une requête
        $stmt = $conn->executeQuery("SELECT COUNT(*) FROM $casesTable");
        $count = $stmt->fetchOne();
    }
}
```

---

### 2. DatabaseDriverDetector

**Chemin :** `src/AppBundle/Service/DatabaseDriverDetector.php`

Service de détection et validation des drivers PHP.

#### Méthodes Principales

```php
// Vérifier si un type de DB est supporté
$isSupported = $driverDetector->isDatabaseTypeSupported('postgresql');

// Obtenir les extensions manquantes
$missing = $driverDetector->getMissingExtensions('postgresql');
// Résultat: ['pdo_pgsql', 'pgsql'] si manquantes

// Générer un rapport complet
$report = $driverDetector->generateReport();

// Tester une connexion
$result = $driverDetector->testConnection($connectionParams);
// Résultat: ['success' => true, 'message' => '...']

// Obtenir les drivers PDO disponibles
$drivers = $driverDetector->getAvailablePdoDrivers();
// Résultat: ['pgsql', 'mysql', 'sqlite']

// Obtenir instructions d'installation
$instructions = $driverDetector->getInstallationInstructions('ubuntu', 'postgresql');
```

#### Exemple d'Utilisation

```php
use AppBundle\Service\DatabaseDriverDetector;

class SetupService {
    public function __construct(private DatabaseDriverDetector $detector) {}

    public function validateSetup() {
        // Vérifier PostgreSQL
        if (!$this->detector->isDatabaseTypeSupported('postgresql')) {
            $missing = $this->detector->getMissingExtensions('postgresql');
            throw new \Exception(
                "PostgreSQL not supported. Missing: " . implode(', ', $missing)
            );
        }

        // Générer rapport
        $report = $this->detector->generateReport();

        // Logger les infos
        $this->logger->info('Setup validated', [
            'php_version' => $report['php_version'],
            'supported_databases' => array_keys($report['databases']),
        ]);
    }
}
```

---

## Console Commands

### csweb:check-database-drivers

Commande pour vérifier les drivers disponibles et la configuration.

#### Usage Basique

```bash
# Vérifier les drivers disponibles
php bin/console csweb:check-database-drivers

# Tester les connexions aux bases configurées
php bin/console csweb:check-database-drivers --test-connections

# Output JSON (pour scripts)
php bin/console csweb:check-database-drivers --json
```

#### Exemple de Sortie

```
========================================
CSWeb Database Drivers Check
========================================

System Information
+-----------------------+---------------------------+
| Property              | Value                     |
+-----------------------+---------------------------+
| PHP Version           | 8.1.12                    |
| Operating System      | Linux                     |
| SAPI                  | cli                       |
| Loaded Extensions     | 87                        |
+-----------------------+---------------------------+

Database Drivers
+------------+--------------+----------------------+
| Database   | Status       | Extensions           |
+------------+--------------+----------------------+
| POSTGRESQL | ✅ Available | ✅ pdo               |
|            |              | ✅ pdo_pgsql         |
|            |              | ✅ pgsql             |
+------------+--------------+----------------------+
| MYSQL      | ✅ Available | ✅ pdo               |
|            |              | ✅ pdo_mysql         |
|            |              | ✅ mysqli            |
+------------+--------------+----------------------+
| SQLSERVER  | ❌ Missing   | ✅ pdo               |
|            |              | ❌ pdo_sqlsrv        |
|            |              | ❌ sqlsrv            |
+------------+--------------+----------------------+

Current Breakout Configuration
Default Database Type: postgresql

+------------+-----------+-----------+------+-------------------+------------------+
| Type       | Driver    | Host      | Port | Database          | User             |
+------------+-----------+-----------+------+-------------------+------------------+
| POSTGRESQL | pdo_pgsql | localhost | 5432 | csweb_analytics   | csweb_analytics  |
| MYSQL      | pdo_mysql | localhost | 3306 | csweb_metadata    | csweb            |
+------------+-----------+-----------+------+-------------------+------------------+

Installation Instructions (si extensions manquantes)
For sqlserver:
  sudo apt-get update
  sudo apt-get install -y php-sqlsrv php-pdo_sqlsrv

Summary
✅ All database types are supported (2/3)

Available for breakout:
  ✅ POSTGRESQL
  ✅ MYSQL
```

#### Test de Connexions

```bash
php bin/console csweb:check-database-drivers --test-connections
```

**Sortie :**

```
Connection Tests
Testing connection to postgresql... ✅ SUCCESS
  └─ Connection successful to localhost:5432/csweb_analytics

Testing connection to mysql... ✅ SUCCESS
  └─ Connection successful to localhost:3306/csweb_metadata

Testing connection to sqlserver... ❌ FAILED
  └─ Connection failed: could not find driver
```

---

## Utilisation

### Scénario 1 : Un Dictionnaire avec PostgreSQL (Default)

**Contexte :** Vous avez un dictionnaire `SURVEY_DICT` et voulez utiliser PostgreSQL.

**Configuration `.env` :**

```bash
DEFAULT_BREAKOUT_DB_TYPE=postgresql
POSTGRES_HOST=localhost
POSTGRES_PORT=5432
POSTGRES_DATABASE=csweb_analytics
POSTGRES_USER=csweb_analytics
POSTGRES_PASSWORD=secure_password
```

**Code :**

```php
// Le service utilisera automatiquement PostgreSQL (défaut)
$config = $breakoutDbConfig->getDatabaseConfigForDictionary('SURVEY_DICT');
// Résultat: config PostgreSQL

// Les tables créées seront:
// survey_cases, survey_level_1, survey_level_2, survey_record_001, etc.
```

**Console Command :**

```bash
php bin/console csweb:process-cases-by-dict dictionnaires=SURVEY_DICT
```

**Exemple du projet Kairos (ANSD):**
```bash
php bin/console csweb:process-cases-by-dict dictionnaires=KAIROS_DICT
# Tables créées: kairos_cases, kairos_level_1, kairos_level_2, kairos_record_001
```

---

### Scénario 2 : Deux Dictionnaires, Deux Bases Différentes

**Contexte :**
- `SURVEY_DICT` → PostgreSQL
- `CENSUS_DICT` → MySQL

**Configuration `.env` :**

```bash
DEFAULT_BREAKOUT_DB_TYPE=postgresql

# PostgreSQL pour SURVEY
POSTGRES_HOST=localhost
POSTGRES_DATABASE=survey_db

# MySQL pour CENSUS
MYSQL_HOST=localhost
MYSQL_DATABASE=census_db
```

**Code :**

```php
// Associer CENSUS_DICT à MySQL
$breakoutDbConfig->setDictionaryDatabase('CENSUS_DICT', 'mysql');

// Obtenir configs
$surveyConfig = $breakoutDbConfig->getDatabaseConfigForDictionary('SURVEY_DICT');
// → PostgreSQL (défaut)

$censusConfig = $breakoutDbConfig->getDatabaseConfigForDictionary('CENSUS_DICT');
// → MySQL (mappé)

// Tables créées:
// PostgreSQL: survey_cases, survey_level_1, ...
// MySQL: census_cases, census_level_1, ...
```

**Exemple du projet Kairos (ANSD):**
```php
// KAIROS_DICT → PostgreSQL, EVAL_PRODUCTEURS → MySQL
$breakoutDbConfig->setDictionaryDatabase('EVAL_PRODUCTEURS', 'mysql');
// Tables: kairos_cases (PostgreSQL), eval_producteurs_cases (MySQL)
```

---

### Scénario 3 : Tous les Dictionnaires en MySQL

**Contexte :** Vous préférez utiliser MySQL pour tout.

**Configuration `.env` :**

```bash
# Changer le type par défaut
DEFAULT_BREAKOUT_DB_TYPE=mysql

MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_DATABASE=csweb_breakout_all
MYSQL_USER=csweb_breakout
MYSQL_PASSWORD=secure_password
```

**Résultat :** Tous les dictionnaires utiliseront MySQL sauf si explicitement mappés.

---

### Scénario 4 : Migration d'un Dictionnaire de MySQL vers PostgreSQL

**Étape 1 : Dump MySQL**

```bash
mysqldump -u csweb -p survey_db > survey_dump.sql
```

**Étape 2 : Convertir le dump pour PostgreSQL**

Utiliser un outil comme `mysql2pgsql` ou convertir manuellement.

**Étape 3 : Importer dans PostgreSQL**

```bash
psql -U csweb_analytics -d csweb_analytics < survey_dump_converted.sql
```

**Étape 4 : Changer le mapping**

```php
$breakoutDbConfig->setDictionaryDatabase('SURVEY_DICT', 'postgresql');
```

**Étape 5 : Tester**

```bash
php bin/console csweb:check-database-drivers --test-connections
```

**Exemple du projet Kairos (ANSD):**
```bash
# Migration de kairos_db de MySQL vers PostgreSQL
mysqldump -u kairos_dev -p kairos_db > kairos_dump.sql
# Conversion et import...
$breakoutDbConfig->setDictionaryDatabase('KAIROS_DICT', 'postgresql');
```

---

## Exemples Pratiques

### Exemple 1 : Vérifier les Prérequis au Démarrage

**Fichier :** `src/AppBundle/EventListener/StartupListener.php`

```php
<?php

namespace AppBundle\EventListener;

use AppBundle\Service\DatabaseDriverDetector;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class StartupListener
{
    public function __construct(
        private DatabaseDriverDetector $driverDetector,
        private LoggerInterface $logger
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Vérifier PostgreSQL (requis)
        if (!$this->driverDetector->isDatabaseTypeSupported('postgresql')) {
            $missing = $this->driverDetector->getMissingExtensions('postgresql');

            $this->logger->critical('PostgreSQL driver not available', [
                'missing_extensions' => $missing,
            ]);

            // Optionnel: lever une exception
            // throw new \RuntimeException('PostgreSQL driver required');
        }
    }
}
```

---

### Exemple 2 : Sélecteur de Base de Données dans un Formulaire

**Controller :**

```php
use AppBundle\Service\BreakoutDatabaseConfig;
use Symfony\Component\HttpFoundation\Request;

class DictionaryController extends Controller
{
    public function configureDatabaseAction(
        Request $request,
        BreakoutDatabaseConfig $dbConfig
    ) {
        $dictionaryName = $request->get('dictionary');
        $availableTypes = $dbConfig->getAvailableDatabaseTypes();

        // Afficher formulaire avec choix de base
        return $this->render('dictionary/configure_db.html.twig', [
            'dictionary' => $dictionaryName,
            'available_types' => $availableTypes,
        ]);
    }

    public function saveDatabaseConfigAction(
        Request $request,
        BreakoutDatabaseConfig $dbConfig
    ) {
        $dictionaryName = $request->get('dictionary');
        $dbType = $request->get('database_type');

        // Sauvegarder le mapping
        $dbConfig->setDictionaryDatabase($dictionaryName, $dbType);

        // Persister dans la base (optionnel)
        // ...

        $this->addFlash('success', "Database configured: $dbType");

        return $this->redirectToRoute('dictionary_list');
    }
}
```

**Template Twig :**

```twig
{# templates/dictionary/configure_db.html.twig #}
<h2>Configure Database for {{ dictionary }}</h2>

<form method="post" action="{{ path('dictionary_save_db_config') }}">
    <input type="hidden" name="dictionary" value="{{ dictionary }}">

    <label>Select Database Type:</label>
    <select name="database_type">
        {% for dbType in available_types %}
            <option value="{{ dbType }}">{{ dbType|upper }}</option>
        {% endfor %}
    </select>

    <button type="submit">Save Configuration</button>
</form>
```

---

### Exemple 3 : API Endpoint pour Tester une Connexion

**Controller :**

```php
use AppBundle\Service\DatabaseDriverDetector;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ApiController extends Controller
{
    /**
     * POST /api/test-database-connection
     *
     * Body: {
     *   "driver": "pdo_pgsql",
     *   "host": "localhost",
     *   "port": 5432,
     *   "dbname": "test_db",
     *   "user": "user",
     *   "password": "password"
     * }
     */
    public function testConnectionAction(
        Request $request,
        DatabaseDriverDetector $detector
    ): JsonResponse {
        $params = json_decode($request->getContent(), true);

        $result = $detector->testConnection($params);

        return new JsonResponse([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 400);
    }
}
```

---

## Troubleshooting

### Problème 1 : Extension pdo_pgsql manquante

**Symptôme :**

```
SQLSTATE[HY000]: could not find driver
```

**Solution :**

```bash
# Ubuntu/Debian
sudo apt-get install php-pgsql
sudo systemctl restart apache2

# Vérifier
php -m | grep pdo_pgsql
```

---

### Problème 2 : Connexion refusée à PostgreSQL

**Symptôme :**

```
SQLSTATE[08006]: Connection refused
```

**Solutions :**

1. **Vérifier que PostgreSQL est démarré :**

```bash
sudo systemctl status postgresql
sudo systemctl start postgresql
```

2. **Vérifier pg_hba.conf :**

```bash
sudo nano /etc/postgresql/14/main/pg_hba.conf
```

Ajouter :

```
# IPv4 local connections:
host    all             all             127.0.0.1/32            md5
```

3. **Redémarrer PostgreSQL :**

```bash
sudo systemctl restart postgresql
```

---

### Problème 3 : Mot de passe incorrect

**Symptôme :**

```
SQLSTATE[28P01]: password authentication failed
```

**Solution :**

1. **Vérifier le .env :**

```bash
cat .env | grep POSTGRES_PASSWORD
```

2. **Réinitialiser le mot de passe PostgreSQL :**

```bash
sudo -u postgres psql
postgres=# ALTER USER csweb_analytics PASSWORD 'new_password';
postgres=# \q
```

3. **Mettre à jour .env avec le nouveau mot de passe**

---

### Problème 4 : Base de données inexistante

**Symptôme :**

```
SQLSTATE[08006]: database "csweb_analytics" does not exist
```

**Solution :**

```bash
# Créer la base
sudo -u postgres psql
postgres=# CREATE DATABASE csweb_analytics;
postgres=# GRANT ALL PRIVILEGES ON DATABASE csweb_analytics TO csweb_analytics;
postgres=# \q
```

---

### Problème 5 : Permissions insuffisantes

**Symptôme :**

```
SQLSTATE[42501]: insufficient privilege
```

**Solution :**

```bash
sudo -u postgres psql csweb_analytics
csweb_analytics=# GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO csweb_analytics;
csweb_analytics=# GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO csweb_analytics;
csweb_analytics=# \q
```

---

## FAQ

### Q1 : Puis-je utiliser plusieurs bases PostgreSQL pour différents dictionnaires ?

**R :** Oui ! Créez plusieurs bases et utilisez des variables d'environnement différentes :

```bash
# .env
POSTGRES_KAIROS_HOST=localhost
POSTGRES_KAIROS_DATABASE=kairos_db

POSTGRES_CENSUS_HOST=remote-server
POSTGRES_CENSUS_DATABASE=census_db
```

Ensuite, configurez le service pour utiliser ces variables.

---

### Q2 : Comment migrer d'une base à une autre ?

**R :** Voir "Scénario 4" dans la section [Utilisation](#scénario-4--migration-dun-dictionnaire-de-mysql-vers-postgresql).

---

### Q3 : SQL Server est-il vraiment supporté ?

**R :** Oui, mais nécessite l'installation de drivers Microsoft :

```bash
# Ubuntu
curl https://packages.microsoft.com/keys/microsoft.asc | sudo apt-key add -
curl https://packages.microsoft.com/config/ubuntu/20.04/prod.list | sudo tee /etc/apt/sources.list.d/mssql-release.list
sudo apt-get update
sudo ACCEPT_EULA=Y apt-get install -y msodbcsql17
sudo pecl install sqlsrv pdo_sqlsrv
```

---

### Q4 : Comment sauvegarder les mappings dictionnaire → base de données ?

**R :** Les mappings peuvent être persistés dans une table dédiée :

**Migration SQL :**

```sql
CREATE TABLE cspro_dictionary_database_mappings (
    id SERIAL PRIMARY KEY,
    dictionary_name VARCHAR(255) NOT NULL UNIQUE,
    database_type VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Service augmenté :**

```php
public function loadMappingsFromDatabase(): void {
    $stmt = $this->pdo->query('SELECT dictionary_name, database_type FROM cspro_dictionary_database_mappings');
    $mappings = $stmt->fetchAll();

    foreach ($mappings as $mapping) {
        $this->setDictionaryDatabase($mapping['dictionary_name'], $mapping['database_type']);
    }
}
```

---

### Q5 : Performance : PostgreSQL vs MySQL ?

**R :**

| Critère | PostgreSQL | MySQL |
|---------|-----------|-------|
| **Conformité SQL** | ⭐⭐⭐⭐⭐ Excellent | ⭐⭐⭐ Bon |
| **Support JSON** | ⭐⭐⭐⭐⭐ Natif, performant | ⭐⭐⭐ Basique |
| **Transactions** | ⭐⭐⭐⭐⭐ ACID complet | ⭐⭐⭐⭐ ACID (InnoDB) |
| **Performance OLAP** | ⭐⭐⭐⭐⭐ Excellent | ⭐⭐⭐ Moyen |
| **Performance OLTP** | ⭐⭐⭐⭐ Très bon | ⭐⭐⭐⭐⭐ Excellent |
| **Facilité setup** | ⭐⭐⭐ Moyen | ⭐⭐⭐⭐⭐ Très facile |

**Recommandation :** PostgreSQL pour breakout analytics (requêtes complexes, JSON), MySQL pour métadonnées CSWeb (lectures/écritures simples).

---

## Conclusion

Cette couche de configuration multi-database apporte **l'adaptabilité à chaud** nécessaire pour déployer CSWeb 8 dans n'importe quel environnement, avec n'importe quel choix de base de données.

### Points Clés

✅ **Flexibilité totale** : PostgreSQL, MySQL ou SQL Server au choix
✅ **Détection automatique** : Vérification des modules PHP requis
✅ **Configuration centralisée** : Tout dans `.env`
✅ **Zero downtime** : Changement de base sans redémarrage
✅ **Production-ready** : Tests de connexion, gestion d'erreurs

### Prochaines Étapes

1. **Interface d'administration web** : Gérer les mappings via UI
2. **Auto-migration** : Scripts de migration automatique entre bases
3. **Monitoring** : Dashboard de santé des connexions DB
4. **Backup automatique** : Sauvegarde multi-base

---

## Support

**Questions sur cette configuration ?**

- 📧 bounafode@gmail.com
- 💬 GitHub Discussions
- 🐛 GitHub Issues

---

**Made with ❤️ by Bouna DRAME**

**CSWeb Community Platform - Démocratiser CSWeb pour l'Afrique**
