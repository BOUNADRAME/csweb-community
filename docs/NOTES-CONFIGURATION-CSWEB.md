---
layout: default
title: NOTES CONFIGURATION CSWeb
---

# Notes Importantes : Configuration CSWeb

> **Documentation sur la configuration MySQL de CSWeb (à NE PAS modifier)**

**Auteur :** Bouna DRAME
**Date :** 14 Mars 2026

---

## ⚠️ IMPORTANT : Base de Données CSWeb

### Configuration MySQL de CSWeb (NE PAS MODIFIER)

CSWeb utilise **MySQL** comme base de données **métadonnées** lors de sa création via `setup.php`.

**Cette configuration MySQL ne doit JAMAIS être modifiée dans la couche multi-database.**

### Exemple de Configuration CSWeb

**Fichier :** `/var/www/html/csweb/src/AppBundle/config.php`

```php
<?php
define('DBHOST', 'localhost');
define('DBUSER', 'csweb_user');
define('DBPASS', 'secure_password_here');
define('DBNAME', 'csweb_metadata');
define('ENABLE_OAUTH', true);
define('FILES_FOLDER', '/var/www/html/csweb/files');
define('DEFAULT_TIMEZONE', 'UTC');
define('MAX_EXECUTION_TIME', '300');
define('API_URL', 'http://csweb.example.com/api/');
define('CSWEB_LOG_LEVEL' , 'error');
define('CSWEB_PROCESS_CASES_LOG_LEVEL', 'error');
?>
```

**Exemple :**
```php
define('DBHOST', 'localhost');
define('DBNAME', 'csweb_metadata');
define('API_URL', 'http://localhost:8080/api/');
```

---

## Architecture Bases de Données

### Séparation des Responsabilités

CSWeb utilise **deux types de bases de données** avec des responsabilités distinctes :

```
┌─────────────────────────────────────────────────────────┐
│                    CSWeb Application                     │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌────────────────────────────────────────────────┐    │
│  │  MYSQL (Métadonnées CSWeb) - config.php        │    │
│  │  ----------------------------------------       │    │
│  │  - Dictionnaires CSPro                         │    │
│  │  - Utilisateurs et permissions                 │    │
│  │  - Cases synchronisés depuis devices           │    │
│  │  - Fichiers médias (références)                │    │
│  │  - Configuration OAuth                         │    │
│  │  - Logs applicatifs                            │    │
│  │                                                 │    │
│  │  ⚠️ NE JAMAIS MODIFIER CETTE CONFIG            │    │
│  └────────────────────────────────────────────────┘    │
│                          ↓                              │
│  ┌────────────────────────────────────────────────┐    │
│  │  POSTGRESQL/MYSQL/SQL SERVER (Breakout)        │    │
│  │  ----------------------------------------       │    │
│  │  - Tables de breakout par dictionnaire         │    │
│  │  - Schémas relationnels (kairos_*, census_*)   │    │
│  │  - Données analytiques                         │    │
│  │  - Reports et aggregations                     │    │
│  │                                                 │    │
│  │  ✅ CONFIGURABLE via .env                      │    │
│  └────────────────────────────────────────────────┘    │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## Configuration Multi-Database

### Ce qui est FIXE (Ne pas toucher)

**Base MySQL CSWeb (Métadonnées) :**

- **Fichier config :** `src/AppBundle/config.php`
- **Créé par :** `setup.php` lors de l'installation initiale
- **Contenu :**
  - `DBHOST` - Hôte MySQL
  - `DBUSER` - Utilisateur MySQL
  - `DBPASS` - Mot de passe MySQL
  - `DBNAME` - Nom de la base (ex: `csweb_metadata`)
- **Tables :**
  - `cspro_dictionaries`
  - `cspro_users`
  - `cspro_files`
  - `cspro_oauth_clients`
  - `DICTIONARY_NAME` (tables cases CSPro)

**⚠️ CRITIQUE :** Cette configuration est essentielle au fonctionnement de CSWeb. Ne jamais la modifier via la couche multi-database.

---

### Ce qui est CONFIGURABLE (.env)

**Bases de Données Breakout (Analytics) :**

- **Fichier config :** `.env`
- **Géré par :** `BreakoutDatabaseConfig` service
- **Variables :**

```bash
# PostgreSQL pour breakout
POSTGRES_HOST=localhost
POSTGRES_PORT=5432
POSTGRES_DATABASE=csweb_analytics
POSTGRES_USER=csweb_analytics
POSTGRES_PASSWORD=secure_password

# MySQL pour breakout (optionnel, différent de MySQL CSWeb)
MYSQL_BREAKOUT_HOST=localhost
MYSQL_BREAKOUT_PORT=3306
MYSQL_BREAKOUT_DATABASE=csweb_breakout
MYSQL_BREAKOUT_USER=csweb_breakout
MYSQL_BREAKOUT_PASSWORD=secure_password

# SQL Server pour breakout (optionnel)
SQLSERVER_HOST=localhost
SQLSERVER_PORT=1433
SQLSERVER_DATABASE=csweb_analytics
SQLSERVER_USER=sa
SQLSERVER_PASSWORD=YourStrong!Passw0rd
```

- **Tables créées :**
  - `{label}_cases` (ex: `kairos_cases`)
  - `{label}_level_1` (ex: `kairos_level_1`)
  - `{label}_record_001` (ex: `kairos_record_001`)
  - etc.

---

## Clarification : Deux MySQL Différents

### MySQL #1 : CSWeb Metadata (FIXE)

```
Serveur: localhost (ou adresse de votre serveur)
Base: csweb_metadata (nom générique)
User: csweb_user
Usage: Métadonnées CSWeb
Config: src/AppBundle/config.php
```

**Exemple du projet Kairos (ANSD):**
```
Serveur: localhost
Base: csweb_metadata
User: root
```

**Rôle :**
- Synchronisation CSPro devices
- Gestion utilisateurs OAuth
- Stockage dictionnaires CSPro
- Fichiers médias (références)

**Tables CSWeb :**
- `cspro_dictionaries`
- `cspro_users`
- `cspro_oauth_clients`
- `cspro_files`
- `cspro_sync_history`
- `{DICTIONARY_NAME}` (tables cases des dictionnaires)

**Exemple du projet Kairos (ANSD):**
- `EVAL_PRODUCTEURS_USAID` (table cases du dictionnaire)

---

### MySQL #2 : Breakout Analytics (OPTIONNEL - Configurable)

```
Serveur: localhost (ou autre)
Base: csweb_breakout
User: csweb_breakout
Usage: Breakout analytics (optionnel, PostgreSQL recommandé)
Config: .env (MYSQL_BREAKOUT_*)
```

**Rôle :**
- Tables de breakout relationnelles
- Analytics et reporting
- Alternative à PostgreSQL si souhaité

**Tables Breakout :**
- `{dictionary_label}_cases` (ex: `survey_cases`, `census_cases`)
- `{dictionary_label}_level_1` (ex: `survey_level_1`, `census_level_1`)
- `{dictionary_label}_record_001` (ex: `survey_record_001`)

**Exemple du projet Kairos (ANSD):**
- `kairos_cases`
- `kairos_level_1`
- `kairos_record_001`

---

## Workflow Complet

### Étape 1 : Installation CSWeb (setup.php)

```bash
# Sur serveur CSWeb distant
cd /var/www/html/csweb
php setup.php

# Crée:
# - src/AppBundle/config.php (MySQL #1)
# - Base de données MySQL pour métadonnées
# - Tables CSWeb (cspro_*)
```

**Résultat :** CSWeb opérationnel avec MySQL métadonnées (FIXE).

---

### Étape 2 : Configuration Breakout (.env)

```bash
# Sur serveur CSWeb ou Kairos API
nano .env

# Configurer PostgreSQL pour breakout (recommandé)
POSTGRES_HOST=localhost
POSTGRES_PORT=5432
POSTGRES_DATABASE=csweb_analytics
POSTGRES_USER=csweb_analytics
POSTGRES_PASSWORD=secure_password

DEFAULT_BREAKOUT_DB_TYPE=postgresql
```

**Résultat :** Breakout utilisera PostgreSQL (ou MySQL #2 si configuré).

---

### Étape 3 : Breakout Sélectif

```bash
# Console command générique
php bin/console csweb:process-cases-by-dict dictionnaires=SURVEY_DICT

# Flux:
# 1. Lit les cases depuis MySQL #1 (csweb_metadata.SURVEY_DICT)
# 2. Extrait label "survey" du nom "SURVEY_DICT"
# 3. Créé tables dans PostgreSQL:
#    - survey_cases
#    - survey_level_1
#    - survey_record_001
# 4. Transforme et insère les données
```

**Exemple du projet Kairos (ANSD):**
```bash
php bin/console csweb:process-cases-by-dict dictionnaires=KAIROS_DICT
# Lit depuis: csweb_metadata.KAIROS_DICT
# Crée tables: kairos_cases, kairos_level_1, kairos_record_001
```

---

## Résumé pour Documentation

### À Documenter dans CONFIGURATION-MULTI-DATABASE.md

✅ **OUI - À documenter :**
- Configuration `.env` pour bases de breakout (PostgreSQL, MySQL #2, SQL Server)
- Service `BreakoutDatabaseConfig`
- Choix du type de base par dictionnaire
- Génération noms de tables avec label

❌ **NON - À NE PAS documenter comme "configurable" :**
- `src/AppBundle/config.php` (créé par setup.php)
- MySQL #1 (métadonnées CSWeb)
- Variables `DBHOST`, `DBUSER`, `DBPASS`, `DBNAME` de config.php

### Note Importante à Ajouter

Dans la documentation multi-database, ajouter une section :

```markdown
## ⚠️ Important : MySQL CSWeb vs Breakout

CSWeb utilise **deux bases de données distinctes** :

1. **MySQL Métadonnées (CSWeb)** - Configuration FIXE
   - Fichier : `src/AppBundle/config.php`
   - Créé par : `setup.php`
   - Usage : Métadonnées CSWeb, sync devices, users
   - **Ne JAMAIS modifier** via couche multi-database

2. **PostgreSQL/MySQL/SQL Server (Breakout)** - Configuration FLEXIBLE
   - Fichier : `.env`
   - Géré par : `BreakoutDatabaseConfig`
   - Usage : Tables relationnelles breakout
   - **Configurable** selon vos besoins

La couche multi-database ne concerne **UNIQUEMENT** les bases de breakout (#2).
```

---

## Exemple Concret : Projet Kairos (ANSD, Sénégal)

> **Note:** Ceci est un exemple réel d'implémentation du système CSWeb Community Platform pour le projet Kairos de l'ANSD (Agence Nationale de la Statistique et de la Démographie du Sénégal).

### Configuration du Projet Kairos

**MySQL Métadonnées (FIXE) :**
```
Serveur: localhost
Base: csweb_metadata
User: root
Password: pasa@kkk (à changer en production!)
Rôle: Métadonnées CSWeb, synchronisation devices
```

**PostgreSQL Breakout (Configurable) :**
```
Serveur: localhost
Base: csweb_analytics
User: csweb_analytics
Schema: app (tables kairos_*)
Rôle: Analytics, reporting, données relationnelles
```

### Workflow Kairos

1. **Devices CSPro** (terrain) → Synchronisation → **MySQL Métadonnées** (csweb_metadata)
2. **Kairos API** (backend) → Déclenche breakout → **PostgreSQL** (csweb_analytics.app)
3. **Frontend React** (tableau de bord) → Lit analytics depuis → **PostgreSQL** (csweb_analytics.app)

**Cette architecture peut être reproduite pour tout projet CSWeb similaire.**

---

## Checklist Migration Vanilla CSWeb → Community

- [ ] **Étape 1 :** Installer CSWeb vanilla avec `setup.php`
  - Crée `config.php` avec MySQL métadonnées
  - Ne PAS toucher à cette config

- [ ] **Étape 2 :** Créer `.env` pour breakout
  - Configurer PostgreSQL (ou MySQL #2 ou SQL Server)
  - `DEFAULT_BREAKOUT_DB_TYPE=postgresql`

- [ ] **Étape 3 :** Modifier code PHP (transformations Assietou)
  - `DictionarySchemaHelper.php`
  - `MySQLQuestionnaireSerializer.php`
  - `MySQLDictionarySchemaGenerator.php`

- [ ] **Étape 4 :** Ajouter services multi-database
  - `BreakoutDatabaseConfig.php`
  - `DatabaseDriverDetector.php`
  - `CheckDatabaseDriversCommand.php`

- [ ] **Étape 5 :** Tester breakout sélectif
  - `php bin/console csweb:check-database-drivers`
  - `php bin/console csweb:process-cases-by-dict dictionnaires=TEST_DICT`

  **Exemple du projet Kairos (ANSD):**
  - `php bin/console csweb:process-cases-by-dict dictionnaires=KAIROS_DICT`

---

## Conclusion

**Règle d'or :**
- **MySQL CSWeb** (config.php) = FIXE, ne jamais toucher
- **Bases Breakout** (.env) = FLEXIBLE, configurable à volonté

**Avantage :**
- Séparation claire des responsabilités
- CSWeb continue de fonctionner normalement
- Flexibilité totale pour le breakout analytics

---

**Auteur :** Bouna DRAME
**Date :** 14 Mars 2026
**Version :** 1.0
