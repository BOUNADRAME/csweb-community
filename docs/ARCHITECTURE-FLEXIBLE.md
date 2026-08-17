---
layout: default
title: Architecture Flexible
---

# Architecture Flexible : Local & Remote

> **Guide complet de l'architecture multi-mode et multi-base de données**

**Version :** 2.0.0
**Date :** 14 Mars 2026
**Auteur :** Bouna DRAME

---

## 📋 Table des Matières

1. [Introduction](#introduction)
2. [Concepts Clés](#concepts-clés)
3. [Architecture](#architecture)
4. [Modes de Déploiement](#modes-de-déploiement)
5. [Bases de Données Supportées](#bases-de-données-supportées)
6. [Scénarios d'Usage](#scénarios-dusage)
7. [Migration à Chaud](#migration-à-chaud)
8. [Configuration](#configuration)
9. [Troubleshooting](#troubleshooting)

---

## Introduction

CSWeb Community Platform v2.0 introduit une **architecture ultra-flexible** permettant de choisir :

✅ **Mode de déploiement** : Local (Docker) ou Remote (serveur distant)
✅ **Type de base de données** : PostgreSQL, MySQL ou SQL Server
✅ **Migration à chaud** : Changement de configuration sans perte de données

### Cas d'Usage Réel : RGPH5 Sénégal

**Contexte :**
- **Serveur CSWeb** : Machine dédiée avec MySQL local (métadonnées)
- **Serveur Breakout** : SQL Server distant (analytics RGPH5)
- **Séparation** : 2 serveurs physiques distincts

Cette architecture est maintenant supportée nativement !

---

## Concepts Clés

### 1️⃣ Deux Bases de Données Distinctes

```
┌─────────────────────────────────────────────────────────────┐
│                    Serveur CSWeb                             │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  MySQL LOCAL (Métadonnées - OBLIGATOIRE)                    │
│  ├─ cspro_dictionaries                                       │
│  ├─ cspro_users                                              │
│  ├─ cspro_oauth_clients                                      │
│  └─ Créé par setup.php                                       │
│                                                               │
└─────────────────────────────────────────────────────────────┘
                           │
                           │ Connexion
                           ↓
┌─────────────────────────────────────────────────────────────┐
│              Base de Données Breakout                        │
│                (LOCAL ou REMOTE)                             │
├─────────────────────────────────────────────────────────────┤
│  PostgreSQL / MySQL / SQL Server                             │
│  ├─ {label}_cases                                            │
│  ├─ {label}_level_1                                          │
│  ├─ {label}_level_2                                          │
│  └─ {label}_record_*                                         │
└─────────────────────────────────────────────────────────────┘
```

**Important :**
- **MySQL Métadonnées** : TOUJOURS local, créé par setup.php, NE JAMAIS MODIFIER
- **Base Breakout** : PEUT être local (Docker) ou remote (serveur distant)

### 2️⃣ Variables de Configuration

```bash
# Essentiel
BREAKOUT_MODE=local|remote        # Où est la DB breakout
BREAKOUT_DB_TYPE=postgresql|mysql|sqlserver  # Type de DB
```

### 3️⃣ Drivers Installés

**Tous les drivers sont installés dans l'image Docker :**

✅ `pdo_mysql` - MySQL
✅ `pdo_pgsql` - PostgreSQL
✅ `sqlsrv`, `pdo_sqlsrv` - SQL Server

**Avantage :** Changement à chaud sans rebuild !

---

## Architecture

### Mode LOCAL (Développement/Test)

```
┌──────────────────────────────────────────────────────────────┐
│                   Docker Compose Stack                        │
├──────────────────────────────────────────────────────────────┤
│                                                                │
│  ┌─────────────┐   ┌─────────────┐   ┌────────────────────┐ │
│  │   CSWeb     │───│   MySQL     │   │  PostgreSQL/MySQL  │ │
│  │  (Apache)   │   │ (Metadata)  │   │  /SQL Server       │ │
│  │             │   │             │   │  (Breakout)        │ │
│  └─────────────┘   └─────────────┘   └────────────────────┘ │
│                                                                │
│  Tous les services sur la même machine                        │
│  Démarrage : docker-compose --profile local-{type} up -d     │
│                                                                │
└──────────────────────────────────────────────────────────────┘
```

**Commandes selon type :**

```bash
# PostgreSQL
docker-compose --profile local-postgres up -d

# MySQL
docker-compose --profile local-mysql up -d

# SQL Server
docker-compose --profile local-sqlserver up -d
```

### Mode REMOTE (Production)

```
┌──────────────────────────────────────────────────────────────┐
│                   Serveur CSWeb (Docker)                      │
├──────────────────────────────────────────────────────────────┤
│                                                                │
│  ┌─────────────┐   ┌─────────────┐                           │
│  │   CSWeb     │───│   MySQL     │                           │
│  │  (Apache)   │   │ (Metadata)  │                           │
│  └─────────────┘   └─────────────┘                           │
│                                                                │
└──────────────────────────────────────────────────────────────┘
                           │
                           │ TCP/IP
                           ↓
┌──────────────────────────────────────────────────────────────┐
│              Serveur Base de Données Distant                  │
├──────────────────────────────────────────────────────────────┤
│  PostgreSQL / MySQL / SQL Server                              │
│  IP: 192.168.1.100                                            │
└──────────────────────────────────────────────────────────────┘
```

**Commande :**

```bash
# Uniquement CSWeb + MySQL metadata
docker-compose up -d csweb mysql
```

---

## Modes de Déploiement

### LOCAL

**Quand utiliser :**
- ✅ Développement
- ✅ Tests
- ✅ POC (Proof of Concept)
- ✅ Environnement isolé
- ✅ Pas d'infrastructure existante

**Avantages :**
- ✅ Installation ultra-rapide (5 min)
- ✅ Tout en local
- ✅ Isolation complète
- ✅ Pas besoin de serveur distant

**Configuration :**

```bash
# .env
BREAKOUT_MODE=local
BREAKOUT_DB_TYPE=postgresql  # ou mysql, sqlserver
```

### REMOTE

**Quand utiliser :**
- ✅ Production
- ✅ Serveur de base de données existant
- ✅ RGPH5 (SQL Server distant)
- ✅ Infrastructure séparée
- ✅ Haute disponibilité

**Avantages :**
- ✅ Utilise infrastructure existante
- ✅ Séparation des responsabilités
- ✅ Scalabilité
- ✅ Backup/HA existants

**Configuration :**

```bash
# .env
BREAKOUT_MODE=remote
BREAKOUT_DB_TYPE=sqlserver
SQLSERVER_HOST=172.16.0.50
SQLSERVER_PORT=1433
SQLSERVER_DATABASE=RGPH5_Analytics
SQLSERVER_USER=sa
SQLSERVER_PASSWORD=ProductionPassword!
```

---

## Bases de Données Supportées

### PostgreSQL (Recommandé)

**Avantages :**
- ✅ Excellent pour analytics
- ✅ JSON natif
- ✅ Window functions
- ✅ Full-text search
- ✅ Performances élevées

**Ports :**
- Local : `5432`
- Variable : `POSTGRES_PORT`

**Configuration locale :**

```bash
BREAKOUT_MODE=local
BREAKOUT_DB_TYPE=postgresql
POSTGRES_HOST=postgres
POSTGRES_PORT=5432
POSTGRES_DATABASE=csweb_analytics
POSTGRES_USER=csweb_analytics
POSTGRES_PASSWORD=secure_password
```

**Configuration remote :**

```bash
BREAKOUT_MODE=remote
BREAKOUT_DB_TYPE=postgresql
POSTGRES_HOST=192.168.1.100
POSTGRES_PORT=5432
POSTGRES_DATABASE=prod_analytics
POSTGRES_USER=analytics_user
POSTGRES_PASSWORD=ProdPassword123!
```

### MySQL

**Avantages :**
- ✅ Performant
- ✅ Familier
- ✅ Infrastructure existante
- ✅ Outils matures

**Ports :**
- Local : `3307` (pour éviter conflit avec MySQL metadata sur 3306)
- Variable : `MYSQL_BREAKOUT_PORT`

**Configuration locale :**

```bash
BREAKOUT_MODE=local
BREAKOUT_DB_TYPE=mysql
MYSQL_BREAKOUT_HOST=mysql-breakout
MYSQL_BREAKOUT_PORT=3307
MYSQL_BREAKOUT_DATABASE=csweb_breakout
MYSQL_BREAKOUT_USER=breakout_user
MYSQL_BREAKOUT_PASSWORD=secure_password
```

**Configuration remote :**

```bash
BREAKOUT_MODE=remote
BREAKOUT_DB_TYPE=mysql
MYSQL_BREAKOUT_HOST=10.20.30.40
MYSQL_BREAKOUT_PORT=3306
MYSQL_BREAKOUT_DATABASE=prod_breakout
MYSQL_BREAKOUT_USER=breakout_user
MYSQL_BREAKOUT_PASSWORD=ProdPassword123!
```

### SQL Server

**Avantages :**
- ✅ Enterprise
- ✅ Robuste
- ✅ RGPH5 Sénégal
- ✅ Infrastructure Microsoft

**Ports :**
- Local : `1433`
- Variable : `SQLSERVER_PORT`

**Configuration locale :**

```bash
BREAKOUT_MODE=local
BREAKOUT_DB_TYPE=sqlserver
SQLSERVER_HOST=sqlserver
SQLSERVER_PORT=1433
SQLSERVER_DATABASE=CSWeb_Analytics
SQLSERVER_USER=sa
SQLSERVER_PASSWORD=YourStrong!Passw0rd
```

**Configuration remote (RGPH5) :**

```bash
BREAKOUT_MODE=remote
BREAKOUT_DB_TYPE=sqlserver
SQLSERVER_HOST=172.16.0.50
SQLSERVER_PORT=1433
SQLSERVER_DATABASE=RGPH5_Analytics
SQLSERVER_USER=sa
SQLSERVER_PASSWORD=RGPH5ProductionPassword!
```

---

## Scénarios d'Usage

### Scénario 1 : Développeur Local

**Contexte :** Développeur qui teste CSWeb en local

**Configuration :**

```bash
# .env
BREAKOUT_MODE=local
BREAKOUT_DB_TYPE=postgresql
```

**Installation :**

```bash
./install.sh
# Choisir: 1) Local, 1) PostgreSQL
```

**Résultat :**
- CSWeb sur http://localhost:8080
- MySQL metadata en local
- PostgreSQL breakout en local
- Tout en Docker, isolation complète

---

### Scénario 2 : Institut National de Statistique (Production)

**Contexte :** INS avec serveur PostgreSQL dédié pour analytics

**Architecture :**
- Serveur 1 : CSWeb (Docker)
- Serveur 2 : PostgreSQL 16 (dédié analytics)

**Configuration :**

```bash
# .env
BREAKOUT_MODE=remote
BREAKOUT_DB_TYPE=postgresql
POSTGRES_HOST=postgres-analytics.ins.local
POSTGRES_PORT=5432
POSTGRES_DATABASE=recensement_analytics
POSTGRES_USER=recensement_user
POSTGRES_PASSWORD=ProductionPassword123!
```

**Installation :**

```bash
./install.sh
# Choisir: 2) Remote, 1) PostgreSQL
# Remplir les credentials du serveur distant
```

**Commande Docker :**

```bash
docker-compose up -d csweb mysql
# Ne démarre PAS postgres, utilise le serveur distant
```

---

### Scénario 3 : RGPH5 Sénégal (SQL Server Distant)

**Contexte :** RGPH5 avec SQL Server enterprise

**Architecture :**
- Serveur 1 : CSWeb (172.16.0.10)
- Serveur 2 : SQL Server 2022 (172.16.0.50)

**Configuration :**

```bash
# .env
BREAKOUT_MODE=remote
BREAKOUT_DB_TYPE=sqlserver
SQLSERVER_HOST=172.16.0.50
SQLSERVER_PORT=1433
SQLSERVER_DATABASE=RGPH5_Analytics
SQLSERVER_USER=sa
SQLSERVER_PASSWORD=RGPH5SecurePassword!
```

**Installation :**

```bash
./install.sh
# Choisir: 2) Remote, 3) SQL Server
# Remplir: 172.16.0.50, RGPH5_Analytics, etc.
```

**Vérification connexion :**

```bash
docker-compose exec csweb php bin/console csweb:check-database-drivers --test-connections
```

---

## Migration à Chaud

### Local → Remote (Mise en Production)

**Situation :** Développé en local, déploiement en production

**Étape 1 : Développement (Local)**

```bash
# .env
BREAKOUT_MODE=local
BREAKOUT_DB_TYPE=mysql

# Démarrage
docker-compose --profile local-mysql up -d
```

**Étape 2 : Export données (optionnel)**

```bash
# Exporter données de développement
docker-compose exec mysql-breakout mysqldump \
  -u breakout_user -p csweb_breakout > dev_backup.sql

# Importer sur serveur prod
mysql -h prod-server.com -u breakout_user -p prod_breakout < dev_backup.sql
```

**Étape 3 : Modification .env**

```bash
# .env (MODIFIER)
BREAKOUT_MODE=remote  # ← Changé
BREAKOUT_DB_TYPE=mysql
MYSQL_BREAKOUT_HOST=prod-server.com  # ← Changé
MYSQL_BREAKOUT_PORT=3306
MYSQL_BREAKOUT_DATABASE=prod_breakout  # ← Changé
MYSQL_BREAKOUT_USER=breakout_user
MYSQL_BREAKOUT_PASSWORD=ProdPassword!  # ← Changé
```

**Étape 4 : Redémarrage**

```bash
# Arrêter
docker-compose down

# Redémarrer sans MySQL breakout
docker-compose up -d csweb mysql
```

**Résultat :**
- ✅ CSWeb se connecte au serveur MySQL distant
- ✅ Pas de perte de données
- ✅ Migration transparente

---

### PostgreSQL → SQL Server

**Situation :** Changement de SGBD

**Étape 1 : Export PostgreSQL**

```bash
docker-compose exec postgres pg_dump \
  -U csweb_analytics csweb_analytics > postgres_backup.sql
```

**Étape 2 : Conversion et Import SQL Server**

```sql
-- Convertir le dump PostgreSQL en T-SQL
-- Importer dans SQL Server
```

**Étape 3 : Modification .env**

```bash
# .env
BREAKOUT_DB_TYPE=sqlserver  # ← Changé de postgresql
SQLSERVER_HOST=sqlserver-prod.com
SQLSERVER_DATABASE=CSWeb_Analytics
```

**Étape 4 : Redémarrage**

```bash
docker-compose down
docker-compose --profile local-sqlserver up -d  # ou remote
```

---

## Configuration

### Fichier .env Complet

Voir `.env.example` pour template avec tous les scénarios commentés.

### Variables Essentielles

| Variable | Valeurs | Description |
|----------|---------|-------------|
| `BREAKOUT_MODE` | `local`, `remote` | Mode de déploiement |
| `BREAKOUT_DB_TYPE` | `postgresql`, `mysql`, `sqlserver` | Type de SGBD |

### Variables PostgreSQL

| Variable | Défaut Local | Exemple Remote |
|----------|--------------|----------------|
| `POSTGRES_HOST` | `postgres` | `192.168.1.100` |
| `POSTGRES_PORT` | `5432` | `5432` |
| `POSTGRES_DATABASE` | `csweb_analytics` | `prod_analytics` |
| `POSTGRES_USER` | `csweb_analytics` | `analytics_user` |
| `POSTGRES_PASSWORD` | (généré) | `ProdPass123!` |

### Variables MySQL Breakout

| Variable | Défaut Local | Exemple Remote |
|----------|--------------|----------------|
| `MYSQL_BREAKOUT_HOST` | `mysql-breakout` | `10.20.30.40` |
| `MYSQL_BREAKOUT_PORT` | `3307` | `3306` |
| `MYSQL_BREAKOUT_DATABASE` | `csweb_breakout` | `prod_breakout` |
| `MYSQL_BREAKOUT_USER` | `breakout_user` | `breakout_user` |
| `MYSQL_BREAKOUT_PASSWORD` | (généré) | `ProdPass123!` |

### Variables SQL Server

| Variable | Défaut Local | Exemple Remote (RGPH5) |
|----------|--------------|------------------------|
| `SQLSERVER_HOST` | `sqlserver` | `172.16.0.50` |
| `SQLSERVER_PORT` | `1433` | `1433` |
| `SQLSERVER_DATABASE` | `CSWeb_Analytics` | `RGPH5_Analytics` |
| `SQLSERVER_USER` | `sa` | `sa` |
| `SQLSERVER_PASSWORD` | `YourStrong!Passw0rd` | `RGPH5SecurePass!` |

---

## Troubleshooting

### Erreur : Cannot connect to remote database

**Symptôme :**

```
Connection refused: 192.168.1.100:5432
```

**Solutions :**

1. **Vérifier réseau :**

```bash
ping 192.168.1.100
telnet 192.168.1.100 5432
```

2. **Vérifier firewall serveur distant :**

```bash
# PostgreSQL
sudo ufw allow 5432/tcp

# MySQL
sudo ufw allow 3306/tcp

# SQL Server
sudo ufw allow 1433/tcp
```

3. **Vérifier configuration serveur distant :**

```bash
# PostgreSQL: autoriser connexions externes
# Éditer postgresql.conf
listen_addresses = '*'

# Éditer pg_hba.conf
host all all 0.0.0.0/0 md5
```

4. **Vérifier credentials :**

```bash
# Tester connexion
psql -h 192.168.1.100 -U analytics_user -d prod_analytics
```

---

### Erreur : Wrong database type

**Symptôme :**

```
SQLSTATE[HY000]: Driver not found for pdo_sqlsrv
```

**Cause :** `BREAKOUT_DB_TYPE` ne correspond pas aux variables configurées

**Solution :**

```bash
# Vérifier cohérence
BREAKOUT_DB_TYPE=sqlserver  # ← Doit correspondre
SQLSERVER_HOST=...          # ← Variables SQL Server doivent être remplies
```

---

### Erreur : Port already in use (local mode)

**Symptôme :**

```
Error: port 5432 is already allocated
```

**Cause :** PostgreSQL déjà installé en local sur port 5432

**Solution :**

1. **Arrêter PostgreSQL local :**

```bash
sudo systemctl stop postgresql
```

2. **Ou changer port Docker :**

```bash
# .env
POSTGRES_PORT=5433

# docker-compose.yml
ports:
  - "5433:5432"
```

---

## Commandes Utiles

### Vérifier Drivers

```bash
docker-compose exec csweb php bin/console csweb:check-database-drivers
```

### Tester Connexions

```bash
docker-compose exec csweb php bin/console csweb:check-database-drivers --test-connections
```

### Voir Configuration Active

```bash
docker-compose exec csweb env | grep BREAKOUT
docker-compose exec csweb env | grep POSTGRES
docker-compose exec csweb env | grep SQLSERVER
```

### Changer Mode à Chaud

```bash
# 1. Modifier .env
nano .env

# 2. Redémarrer CSWeb uniquement
docker-compose restart csweb
```

---

## Ressources

- **Installation :** [QUICK-START.md](../README.md#quick-start)
- **Docker Deployment :** [DOCKER-DEPLOYMENT.md](DOCKER-DEPLOYMENT.md)
- **Migration Breakout :** [MIGRATION-BREAKOUT-SELECTIF.md](MIGRATION-BREAKOUT-SELECTIF.md)
- **Configuration Multi-DB :** [CONFIGURATION-MULTI-DATABASE.md](CONFIGURATION-MULTI-DATABASE.md)

---

**CSWeb Community Platform v2.0 - Architecture Flexible**

Made with ❤️ by Bouna DRAME
