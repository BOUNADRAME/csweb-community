---
layout: default
title: INSTALLATION CSWeb VANILLA
---

# Installation CSWeb Vanilla - Procédure Standard

> **Guide d'installation de base CSWeb (étape préalable avant migration)**

**Auteur :** Bouna DRAME
**Date :** 14 Mars 2026
**Version :** 1.0.0

---

## 📋 Table des Matières

1. [Introduction](#introduction)
2. [Prérequis](#prérequis)
3. [Installation CSWeb Vanilla](#installation-csweb-vanilla)
4. [Configuration via setup.php](#configuration-via-setupphp)
5. [Vérification Installation](#vérification-installation)
6. [Prochaine Étape : Migration](#prochaine-étape--migration)

---

## Introduction

Ce guide couvre l'installation **CSWeb vanilla** (version officielle téléchargée depuis https://csprousers.org/downloads/).

**Cette étape est REQUISE avant d'appliquer les transformations breakout sélectif.**

### Workflow Complet

```
1. Installation CSWeb Vanilla (ce guide)
   └─ setup/configure.php crée config.php
   └─ MySQL configuré pour métadonnées

2. Migration Breakout Sélectif
   └─ Appliquer transformations (MIGRATION-BREAKOUT-SELECTIF.md)
   └─ Configurer bases breakout (CONFIGURATION-MULTI-DATABASE.md)

3. Production
   └─ CSWeb Community Platform opérationnel
```

---

## Prérequis

### Système

| Composant | Version Minimale | Recommandé |
|-----------|------------------|------------|
| **OS** | Linux, Windows Server | Ubuntu 22.04 LTS |
| **Web Server** | Apache 2.4+ ou Nginx 1.18+ | Apache 2.4 |
| **PHP** | 8.1+ | 8.1 ou 8.2 |
| **MySQL** | 5.5.3+ | 8.0 |
| **RAM** | 2 GB | 4 GB |
| **Disque** | 5 GB | 20 GB |

### Extensions PHP Requises

```bash
# Vérifier extensions installées
php -m

# Extensions requises pour CSWeb
- pdo
- pdo_mysql
- mysqli
- mbstring
- xml
- json
- openssl
- zip
- curl
```

### Installation Extensions (Ubuntu/Debian)

```bash
sudo apt-get update
sudo apt-get install -y \
  php8.1 \
  php8.1-cli \
  php8.1-mysql \
  php8.1-mbstring \
  php8.1-xml \
  php8.1-curl \
  php8.1-zip \
  apache2 \
  mysql-server

# Activer mod_rewrite
sudo a2enmod rewrite

# Redémarrer Apache
sudo systemctl restart apache2
```

---

## Installation CSWeb Vanilla

### Étape 1 : Télécharger CSWeb

```bash
# Aller sur https://csprousers.org/downloads/
# Télécharger "CSWeb 8.0" (dernière version)

# Ou via wget (si lien direct disponible)
cd /tmp
wget https://www.csprousers.org/downloads/csweb/csweb-8.0.zip

# Décompresser
unzip csweb-8.0.zip -d csweb-8.0
```

### Étape 2 : Déployer sur le Serveur Web

```bash
# Déplacer vers le répertoire web
sudo mv csweb-8.0 /var/www/html/csweb

# Définir les permissions
sudo chown -R www-data:www-data /var/www/html/csweb
sudo chmod -R 755 /var/www/html/csweb

# Créer le dossier files (requis)
sudo mkdir -p /var/www/html/csweb/files
sudo chown -R www-data:www-data /var/www/html/csweb/files
sudo chmod -R 775 /var/www/html/csweb/files
```

### Étape 3 : Configurer Apache VirtualHost

**Fichier :** `/etc/apache2/sites-available/csweb.conf`

```apache
<VirtualHost *:80>
    ServerName csweb.example.com
    ServerAlias www.csweb.example.com

    DocumentRoot /var/www/html/csweb

    <Directory /var/www/html/csweb>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/csweb-error.log
    CustomLog ${APACHE_LOG_DIR}/csweb-access.log combined
</VirtualHost>
```

**Activer le site :**

```bash
# Activer le VirtualHost
sudo a2ensite csweb.conf

# Désactiver le site par défaut (optionnel)
sudo a2dissite 000-default.conf

# Tester la configuration
sudo apache2ctl configtest

# Redémarrer Apache
sudo systemctl restart apache2
```

### Étape 4 : Configurer MySQL

```bash
# Se connecter à MySQL
sudo mysql -u root -p

# Dans le prompt MySQL :
```

```sql
-- Créer utilisateur CSWeb
CREATE USER 'csweb_user'@'localhost' IDENTIFIED BY 'secure_password_here';

-- Donner tous les privilèges (nécessaire pour setup.php)
GRANT ALL PRIVILEGES ON *.* TO 'csweb_user'@'localhost' WITH GRANT OPTION;

-- Appliquer les privilèges
FLUSH PRIVILEGES;

-- Quitter
EXIT;
```

**Note :** `setup.php` créera automatiquement la base de données.

---

## Configuration via setup.php

### Accéder au Setup

1. **Ouvrir le navigateur**
   ```
   http://csweb.example.com/setup/
   ```

2. **Vérification des prérequis**

   CSWeb affiche une page de vérification :
   - ✅ Version PHP
   - ✅ Extensions PHP requises
   - ✅ Permissions dossiers

   **Si tout est vert, cliquer "Next"**

### Formulaire de Configuration

**Champs à remplir :**

| Champ | Valeur Exemple | Description |
|-------|----------------|-------------|
| **Database Name** | `csweb_metadata` | Nom de la base MySQL (sera créée) |
| **Hostname** | `localhost` | Hôte MySQL |
| **Database Username** | `csweb_user` | Utilisateur MySQL créé plus haut |
| **Database Password** | `secure_password_here` | Mot de passe MySQL |
| **Administrative Password** | `admin123` | Mot de passe admin CSWeb (min 8 car.) |
| **Files Directory** | `/var/www/html/csweb/files` | Dossier stockage fichiers |
| **API URL** | `http://csweb.example.com/api/` | URL API CSWeb |
| **Timezone** | `Africa/Dakar` | Fuseau horaire |
| **Max Execution Time** | `300` | Timeout PHP (secondes) |

**Cliquer "Submit"**

### Ce que fait setup.php

```
1. Validation des paramètres
   └─ Vérification champs non vides
   └─ Test connexion MySQL

2. Création de la base de données
   └─ CREATE DATABASE csweb_metadata

3. Création des tables CSWeb
   ├─ cspro_dictionaries
   ├─ cspro_dictionaries_schema
   ├─ cspro_sync_history
   ├─ cspro_users
   ├─ cspro_oauth_clients
   ├─ cspro_oauth_access_tokens
   ├─ cspro_config
   └─ ... (autres tables)

4. Création du fichier config.php
   └─ /var/www/html/csweb/src/AppBundle/config.php

5. Création utilisateur admin
   └─ Username: admin
   └─ Password: (celui fourni)
```

### Fichier config.php Généré

**Chemin :** `/var/www/html/csweb/src/AppBundle/config.php`

**Contenu type :**

```php
<?php
define('DBHOST', 'localhost');
define('DBUSER', 'csweb_user');
define('DBPASS', 'secure_password_here');
define('DBNAME', 'csweb_metadata');
define('ENABLE_OAUTH', true);
define('FILES_FOLDER', '/var/www/html/csweb/files');
define('DEFAULT_TIMEZONE', 'Africa/Dakar');
define('MAX_EXECUTION_TIME', '300');
define('API_URL', 'http://csweb.example.com/api/');
define('CSWEB_LOG_LEVEL', 'error');
define('CSWEB_PROCESS_CASES_LOG_LEVEL', 'error');
?>
```

**⚠️ IMPORTANT : Ne JAMAIS modifier ce fichier manuellement après sa création**

**⚠️ IMPORTANT : Ce fichier configure UNIQUEMENT MySQL pour les métadonnées CSWeb**

**⚠️ IMPORTANT : Ce n'est PAS le fichier pour configurer les bases de breakout**

---

## Vérification Installation

### 1. Vérifier que config.php existe

```bash
ls -l /var/www/html/csweb/src/AppBundle/config.php
```

**Résultat attendu :**
```
-rw-r--r-- 1 www-data www-data 500 Mar 14 10:00 config.php
```

### 2. Vérifier la base de données MySQL

```bash
mysql -u csweb_user -p csweb_metadata
```

```sql
-- Lister les tables
SHOW TABLES;

-- Vérifier l'utilisateur admin
SELECT username, role FROM cspro_users;
```

**Résultat attendu :**
```
+---------------------------+
| Tables_in_csweb_metadata  |
+---------------------------+
| cspro_config              |
| cspro_dictionaries        |
| cspro_dictionaries_schema |
| cspro_oauth_access_tokens |
| cspro_oauth_clients       |
| cspro_sync_history        |
| cspro_users               |
+---------------------------+

+----------+-------+
| username | role  |
+----------+-------+
| admin    | admin |
+----------+-------+
```

### 3. Tester la Connexion Web

**Accéder à :**
```
http://csweb.example.com/
```

**Connexion :**
- Username: `admin`
- Password: `admin123` (celui que vous avez défini)

**Page d'accueil attendue :**
- Dashboard CSWeb
- Menu : Dictionaries, Sync, Users, etc.

### 4. Tester l'API

```bash
# Obtenir un token JWT
curl -X POST http://csweb.example.com/api/token \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "csweb",
    "grant_type": "password",
    "username": "admin",
    "password": "admin123"
  }'
```

**Résultat attendu :**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

---

## Prochaine Étape : Migration

### CSWeb Vanilla est Maintenant Installé ✅

**Vous avez maintenant :**
- ✅ CSWeb 8.0 opérationnel
- ✅ MySQL configuré pour métadonnées
- ✅ Fichier `config.php` créé
- ✅ Utilisateur admin créé
- ✅ API fonctionnelle

### Deux Chemins Possibles

#### Option 1 : Utiliser CSWeb Vanilla (sans migration)

**Cas d'usage :**
- Synchronisation simple CSPro devices
- Pas besoin de breakout sélectif
- Un seul dictionnaire à traiter

**Avantages :**
- Setup terminé
- Aucune modification nécessaire

**Limitations :**
- Breakout global uniquement (toutes les tables dans un seul schéma)
- Un seul dictionnaire à la fois
- MySQL uniquement

---

#### Option 2 : Migrer vers CSWeb Community Platform (recommandé)

**Cas d'usage :**
- Plusieurs dictionnaires à traiter simultanément
- Besoin d'isolation des données par dictionnaire
- Analytics avec PostgreSQL
- Multi-threading pour performance

**Étapes suivantes :**

1. **Lire la documentation migration**
   - [MIGRATION-BREAKOUT-SELECTIF.md](MIGRATION-BREAKOUT-SELECTIF.md)
   - [CONFIGURATION-MULTI-DATABASE.md](CONFIGURATION-MULTI-DATABASE.md)
   - [NOTES-CONFIGURATION-CSWEB.md](NOTES-CONFIGURATION-CSWEB.md)

2. **Appliquer les transformations PHP** (1-2 heures)
   - Modifier 3 fichiers (21 méthodes)
   - Créer 1 nouveau fichier
   - Créer 3 services

3. **Configurer les bases de breakout** (30 min)
   - Créer `.env`
   - Configurer PostgreSQL/MySQL/SQL Server
   - Tester connexions

4. **Tester le breakout sélectif** (15 min)
   ```bash
   php bin/console csweb:check-database-drivers
   php bin/console csweb:process-cases-by-dict dictionnaires=TEST_DICT
   ```

**Temps total migration :** ~3-4 heures

---

## Architecture Finale (Après Migration)

```
┌─────────────────────────────────────────────────────────┐
│                    CSWeb Application                     │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌────────────────────────────────────────────────┐    │
│  │  MYSQL Métadonnées (config.php - FIXE)        │    │
│  │  ----------------------------------------      │    │
│  │  Base: csweb_metadata                         │    │
│  │  Tables:                                      │    │
│  │  - cspro_dictionaries                         │    │
│  │  - cspro_users                                │    │
│  │  - cspro_sync_history                         │    │
│  │  - DICTIONARY_NAME (cases CSPro)              │    │
│  │                                                │    │
│  │  ⚠️ NE JAMAIS MODIFIER                        │    │
│  └────────────────────────────────────────────────┘    │
│                          ↓                              │
│  ┌────────────────────────────────────────────────┐    │
│  │  POSTGRESQL/MYSQL Breakout (.env - CONFIG)    │    │
│  │  ----------------------------------------      │    │
│  │  Base: csweb_analytics                        │    │
│  │  Tables:                                      │    │
│  │  - kairos_cases                               │    │
│  │  - kairos_level_1                             │    │
│  │  - census_cases                               │    │
│  │  - census_level_1                             │    │
│  │                                                │    │
│  │  ✅ CONFIGURABLE                              │    │
│  └────────────────────────────────────────────────┘    │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## Troubleshooting Installation

### Problème 1 : setup.php inaccessible

**Symptôme :**
```
404 Not Found
```

**Solutions :**

1. **Vérifier DocumentRoot Apache**
   ```bash
   cat /etc/apache2/sites-available/csweb.conf | grep DocumentRoot
   ```

2. **Vérifier permissions**
   ```bash
   ls -la /var/www/html/csweb/setup/
   ```

3. **Vérifier mod_rewrite**
   ```bash
   apache2ctl -M | grep rewrite
   ```

---

### Problème 2 : Erreur de connexion MySQL

**Symptôme :**
```
SQLSTATE[HY000] [1045] Access denied for user 'csweb_user'@'localhost'
```

**Solutions :**

1. **Vérifier utilisateur MySQL**
   ```bash
   mysql -u csweb_user -p
   ```

2. **Recréer l'utilisateur**
   ```sql
   DROP USER 'csweb_user'@'localhost';
   CREATE USER 'csweb_user'@'localhost' IDENTIFIED BY 'new_password';
   GRANT ALL PRIVILEGES ON *.* TO 'csweb_user'@'localhost' WITH GRANT OPTION;
   FLUSH PRIVILEGES;
   ```

---

### Problème 3 : Dossier files non accessible

**Symptôme :**
```
Files directory is not writable
```

**Solutions :**

```bash
# Créer le dossier
sudo mkdir -p /var/www/html/csweb/files

# Permissions
sudo chown -R www-data:www-data /var/www/html/csweb/files
sudo chmod -R 775 /var/www/html/csweb/files

# Vérifier
ls -ld /var/www/html/csweb/files
```

---

### Problème 4 : Extensions PHP manquantes

**Symptôme :**
```
Missing required PHP extensions: pdo_mysql, mbstring
```

**Solutions :**

```bash
# Installer extensions
sudo apt-get install php8.1-mysql php8.1-mbstring

# Redémarrer Apache
sudo systemctl restart apache2

# Vérifier
php -m | grep -E 'pdo_mysql|mbstring'
```

---

## FAQ

### Q1 : Peut-on réinitialiser la configuration ?

**R :** Oui, mais nécessite de supprimer `config.php` :

```bash
# ATTENTION : Cela supprime toute la configuration
sudo rm /var/www/html/csweb/src/AppBundle/config.php

# Puis retourner sur http://csweb.example.com/setup/
```

### Q2 : Peut-on utiliser PostgreSQL pour les métadonnées CSWeb ?

**R :** Non, CSWeb vanilla nécessite **MySQL** pour les métadonnées.

PostgreSQL peut être utilisé **uniquement pour le breakout** (après migration).

### Q3 : Quelle version de MySQL ?

**R :** Minimum 5.5.3, recommandé 8.0+

```bash
mysql --version
```

### Q4 : Peut-on installer plusieurs CSWeb sur le même serveur ?

**R :** Oui, avec des VirtualHosts différents :

```apache
# CSWeb 1
<VirtualHost *:80>
    ServerName csweb1.example.com
    DocumentRoot /var/www/html/csweb1
</VirtualHost>

# CSWeb 2
<VirtualHost *:80>
    ServerName csweb2.example.com
    DocumentRoot /var/www/html/csweb2
</VirtualHost>
```

Chacun aura son propre `config.php` et sa propre base MySQL.

---

## Checklist Installation Complète

- [ ] **Prérequis**
  - [ ] PHP 8.1+ installé
  - [ ] MySQL 5.5.3+ installé
  - [ ] Apache configuré
  - [ ] Extensions PHP installées

- [ ] **Téléchargement**
  - [ ] CSWeb 8.0 téléchargé
  - [ ] Fichiers décompressés dans `/var/www/html/csweb`

- [ ] **Configuration Serveur**
  - [ ] Permissions définies (www-data)
  - [ ] VirtualHost Apache créé
  - [ ] mod_rewrite activé

- [ ] **MySQL**
  - [ ] Utilisateur créé
  - [ ] Privilèges accordés

- [ ] **Setup CSWeb**
  - [ ] Accès à `/setup/`
  - [ ] Formulaire rempli
  - [ ] `config.php` créé
  - [ ] Base de données créée
  - [ ] Utilisateur admin créé

- [ ] **Vérification**
  - [ ] Connexion web réussie
  - [ ] API testée
  - [ ] Tables MySQL vérifiées

- [ ] **Prochaines Étapes**
  - [ ] Décider : Vanilla ou Migration ?
  - [ ] Si migration : Lire guides (MIGRATION-BREAKOUT-SELECTIF.md, etc.)

---

## Support

**Questions sur l'installation CSWeb vanilla ?**

- 📧 Email : bounafode@gmail.com
- 💬 GitHub Discussions
- 🐛 GitHub Issues

**Documentation officielle CSWeb :**
- https://www.csprousers.org/help/CSWeb/

---

**Made with ❤️ by Bouna DRAME**

**CSWeb Community Platform - Démocratiser CSWeb pour l'Afrique**
