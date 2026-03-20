---
layout: default
title: DOCKER DEPLOYMENT
---

# Docker Deployment Guide

> **Guide complet de déploiement Docker pour CSWeb Community Platform**

**Auteur :** Bouna DRAME
**Date :** 14 Mars 2026
**Version :** 1.0.0

---

## 📋 Table des Matières

1. [Introduction](#introduction)
2. [Architecture](#architecture)
3. [Prérequis](#prérequis)
4. [Installation Rapide](#installation-rapide)
5. [Configuration Détaillée](#configuration-détaillée)
6. [Services](#services)
7. [Volumes](#volumes)
8. [Networks](#networks)
9. [Production](#production)
10. [Troubleshooting](#troubleshooting)

---

## Introduction

CSWeb Community Platform est livré avec une configuration Docker Compose production-ready incluant :

- ✅ CSWeb Application (PHP 8.1 + Apache)
- ✅ PostgreSQL 16 (breakout analytics - par défaut)
- ✅ MySQL 8.0 (métadonnées CSWeb)
- ✅ phpMyAdmin (développement)
- ✅ pgAdmin (développement)
- ✅ Volumes persistants
- ✅ Health checks
- ✅ Network isolation

---

## Architecture

```
┌────────────────────────────────────────────────────────────┐
│                    Docker Compose Stack                     │
├────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────┐   ┌──────────────┐   ┌────────────────┐ │
│  │   CSWeb     │───│    MySQL     │   │   PostgreSQL   │ │
│  │  (Apache)   │   │  (Metadata)  │   │   (Analytics)  │ │
│  │   :8080     │   │    :3306     │   │     :5432      │ │
│  └─────────────┘   └──────────────┘   └────────────────┘ │
│         │                  │                    │          │
│         │                  │                    │          │
│  ┌─────────────────────────────────────────────────────┐  │
│  │            csweb-network (bridge)                   │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  ┌─────────────┐                    ┌────────────────┐    │
│  │ phpMyAdmin  │                    │    pgAdmin     │    │
│  │   :8081     │                    │     :8082      │    │
│  └─────────────┘                    └────────────────┘    │
│  (dev profile)                      (dev profile)         │
│                                                             │
└────────────────────────────────────────────────────────────┘

Volumes Persistants:
├── csweb_files      (fichiers uploadés)
├── csweb_logs       (logs application)
├── mysql_data       (données MySQL)
├── postgres_data    (données PostgreSQL)
└── pgadmin_data     (config pgAdmin)
```

---

## Prérequis

### Système

| Composant | Version Minimale | Recommandé |
|-----------|------------------|------------|
| **Docker** | 20.10+ | Latest |
| **Docker Compose** | 2.0+ | Latest |
| **RAM** | 4 GB | 8 GB |
| **Disque** | 20 GB | 50 GB |
| **CPU** | 2 cores | 4 cores |

### Installation Docker

**Ubuntu/Debian :**
```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER
newgrp docker
```

**macOS :**
```bash
brew install --cask docker
```

**Windows :**
- Télécharger Docker Desktop : https://www.docker.com/products/docker-desktop/

---

## Installation Rapide

### Méthode 1 : Script Automatique (Recommandé)

```bash
git clone https://github.com/BOUNADRAME/csweb-community.git
cd csweb-community
chmod +x install.sh
./install.sh
```

### Méthode 2 : Manuel

```bash
# 1. Cloner
git clone https://github.com/BOUNADRAME/csweb-community.git
cd csweb-community

# 2. Copier .env
cp .env.example .env

# 3. Éditer .env (générer mots de passe sécurisés)
nano .env

# 4. Démarrer
docker-compose up -d

# 5. Vérifier
docker-compose ps
```

---

## Configuration Détaillée

### Fichier .env

Le fichier `.env` contient toute la configuration. **Ne jamais committer ce fichier.**

**Variables principales :**

```bash
# Application
APP_ENV=prod                    # prod|dev
APP_DEBUG=false                 # true|false
CSWEB_PORT=8080                 # Port web

# MySQL (Métadonnées CSWeb - FIXE)
MYSQL_HOST=mysql
MYSQL_DATABASE=csweb_metadata
MYSQL_USER=csweb_user
MYSQL_PASSWORD=secure_password_here
MYSQL_ROOT_PASSWORD=secure_root_password_here

# PostgreSQL (Breakout Analytics - Configurable)
POSTGRES_HOST=postgres
POSTGRES_DATABASE=csweb_analytics
POSTGRES_USER=csweb_analytics
POSTGRES_PASSWORD=secure_password_here

# Breakout
DEFAULT_BREAKOUT_DB_TYPE=postgresql    # postgresql|mysql|sqlserver
```

**Générer mots de passe sécurisés :**

```bash
# MySQL
openssl rand -base64 24

# PostgreSQL
openssl rand -base64 24

# APP_SECRET
openssl rand -hex 32

# JWT_SECRET
openssl rand -base64 32
```

---

## Services

### CSWeb Application

**Image :** `php:8.1-apache`
**Port :** `8080` (configurable via `CSWEB_PORT`)

**Extensions PHP installées :**
- pdo, pdo_mysql, pdo_pgsql
- mysqli, pgsql
- mbstring, xml, zip
- opcache

**Volumes montés :**
- `./` → `/var/www/html` (code application)
- `csweb_files` → `/var/www/html/files` (uploads persistants)
- `csweb_logs` → `/var/www/html/var/logs` (logs persistants)

**Health check :**
```bash
curl -f http://localhost/api/
```

---

### MySQL 8.0

**Image :** `mysql:8.0`
**Port :** `3306` (configurable via `MYSQL_PORT`)

**Rôle :** Métadonnées CSWeb (FIXE - créé par setup.php)

**Tables :**
- `cspro_dictionaries`
- `cspro_users`
- `cspro_oauth_clients`
- `{DICT_NAME}` (tables cases)

**Volume :** `mysql_data` → `/var/lib/mysql`

**Configuration personnalisée :** `docker/mysql/my.cnf`

---

### PostgreSQL 16

**Image :** `postgres:16`
**Port :** `5432` (configurable via `POSTGRES_PORT`)

**Rôle :** Breakout analytics (CONFIGURABLE via .env)

**Tables :**
- `{label}_cases`
- `{label}_level_1`
- `{label}_record_001`

**Volume :** `postgres_data` → `/var/lib/postgresql/data`

**Configuration personnalisée :** `docker/postgres/postgresql.conf`

---

### phpMyAdmin (Dev)

**Image :** `phpmyadmin:latest`
**Port :** `8081` (configurable via `PHPMYADMIN_PORT`)

**Activation :**
```bash
docker-compose --profile dev up -d phpmyadmin
```

**Accès :**
- URL : http://localhost:8081
- Server : `mysql`
- Username : `root`
- Password : (voir `.env` → `MYSQL_ROOT_PASSWORD`)

---

### pgAdmin (Dev)

**Image :** `dpage/pgadmin4:latest`
**Port :** `8082` (configurable via `PGADMIN_PORT`)

**Activation :**
```bash
docker-compose --profile dev up -d pgadmin
```

**Accès :**
- URL : http://localhost:8082
- Email : `admin@csweb.local`
- Password : `admin123`

**Ajouter serveur PostgreSQL :**
1. Servers → Create → Server
2. General : Name = `CSWeb Analytics`
3. Connection :
   - Host : `postgres`
   - Port : `5432`
   - Database : `csweb_analytics`
   - Username : `csweb_analytics`
   - Password : (voir `.env`)

---

## Volumes

### Volumes Persistants

| Volume | Chemin Container | Description |
|--------|------------------|-------------|
| `csweb_files` | `/var/www/html/files` | Fichiers uploadés (dictionnaires, médias) |
| `csweb_logs` | `/var/www/html/var/logs` | Logs application |
| `mysql_data` | `/var/lib/mysql` | Données MySQL |
| `postgres_data` | `/var/lib/postgresql/data` | Données PostgreSQL |
| `pgadmin_data` | `/var/lib/pgadmin` | Configuration pgAdmin |

### Gestion des Volumes

**Lister :**
```bash
docker volume ls | grep csweb
```

**Inspecter :**
```bash
docker volume inspect csweb_mysql_data
```

**Sauvegarder :**
```bash
# MySQL
docker-compose exec mysql mysqldump -u root -p csweb_metadata > backup_mysql.sql

# PostgreSQL
docker-compose exec postgres pg_dump -U csweb_analytics csweb_analytics > backup_postgres.sql
```

**Restaurer :**
```bash
# MySQL
docker-compose exec -T mysql mysql -u root -p csweb_metadata < backup_mysql.sql

# PostgreSQL
docker-compose exec -T postgres psql -U csweb_analytics -d csweb_analytics < backup_postgres.sql
```

**Supprimer (⚠️ DANGER) :**
```bash
docker-compose down -v  # Supprime TOUS les volumes
```

---

## Networks

### csweb-network

**Type :** Bridge
**Isolation :** Services isolés du réseau hôte

**Services connectés :**
- csweb
- mysql
- postgres
- phpmyadmin (dev)
- pgadmin (dev)

**Inspection :**
```bash
docker network inspect csweb_csweb-network
```

---

## Production

### Bonnes Pratiques

#### 1. Sécurité

**Mots de passe forts :**
```bash
# Générer automatiquement
openssl rand -base64 32

# Mise à jour régulière (tous les 90 jours)
```

**Désactiver outils dev :**
```yaml
# Ne PAS démarrer phpmyadmin/pgadmin en prod
docker-compose up -d csweb mysql postgres
```

**Firewall :**
```bash
# Autoriser uniquement port 8080
ufw allow 8080/tcp
ufw deny 3306/tcp
ufw deny 5432/tcp
```

#### 2. Performance

**Ressources Docker :**
```yaml
services:
  csweb:
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 2G
        reservations:
          cpus: '1'
          memory: 1G
```

**Optimisation PostgreSQL :**

Éditer `docker/postgres/postgresql.conf` :
```conf
shared_buffers = 512MB
effective_cache_size = 2GB
maintenance_work_mem = 128MB
```

#### 3. Sauvegarde Automatisée

**Cron job :**
```bash
# /etc/cron.daily/csweb-backup
#!/bin/bash
cd /path/to/csweb
docker-compose exec -T mysql mysqldump -u root -p${MYSQL_ROOT_PASSWORD} csweb_metadata > /backup/mysql_$(date +%Y%m%d).sql
docker-compose exec -T postgres pg_dump -U csweb_analytics csweb_analytics > /backup/postgres_$(date +%Y%m%d).sql

# Supprimer backups > 30 jours
find /backup -name "*.sql" -mtime +30 -delete
```

#### 4. Monitoring

**Logs centralisés :**
```bash
docker-compose logs -f --tail=100
```

**Health checks :**
```bash
docker-compose ps
```

**Métriques système :**
```bash
docker stats
```

#### 5. HTTPS

**Nginx reverse proxy :**

```nginx
server {
    listen 443 ssl http2;
    server_name csweb.example.com;

    ssl_certificate /etc/ssl/certs/csweb.crt;
    ssl_certificate_key /etc/ssl/private/csweb.key;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## Troubleshooting

### Port déjà utilisé

**Erreur :**
```
Error: bind: address already in use
```

**Solution :**

1. Vérifier processus utilisant le port :
```bash
sudo lsof -i :8080
```

2. Changer le port dans `.env` :
```bash
CSWEB_PORT=9080
```

3. Redémarrer :
```bash
docker-compose down
docker-compose up -d
```

---

### Service ne démarre pas

**Vérifier logs :**
```bash
docker-compose logs csweb
docker-compose logs mysql
docker-compose logs postgres
```

**Recréer containers :**
```bash
docker-compose down
docker-compose up -d --force-recreate
```

---

### Permissions fichiers

**Erreur :**
```
Permission denied: /var/www/html/files
```

**Solution :**
```bash
docker-compose exec csweb chown -R www-data:www-data /var/www/html/files
docker-compose exec csweb chmod -R 775 /var/www/html/files
```

---

### Connexion base de données échoue

**MySQL :**
```bash
# Tester connexion
docker-compose exec mysql mysql -u csweb_user -p -e "SHOW DATABASES;"

# Vérifier utilisateur
docker-compose exec mysql mysql -u root -p -e "SELECT user,host FROM mysql.user WHERE user='csweb_user';"
```

**PostgreSQL :**
```bash
# Tester connexion
docker-compose exec postgres psql -U csweb_analytics -d csweb_analytics -c "\dt"

# Vérifier utilisateur
docker-compose exec postgres psql -U csweb_analytics -c "\du"
```

---

## Commandes Utiles

### Gestion Services

```bash
# Démarrer tout
docker-compose up -d

# Démarrer avec dev tools
docker-compose --profile dev up -d

# Arrêter
docker-compose down

# Redémarrer
docker-compose restart

# Reconstruire images
docker-compose build

# Voir statut
docker-compose ps

# Voir logs
docker-compose logs -f

# Logs d'un service
docker-compose logs -f csweb
```

### Accès Shells

```bash
# Shell CSWeb
docker-compose exec csweb bash

# Shell MySQL
docker-compose exec mysql bash

# MySQL client
docker-compose exec mysql mysql -u root -p

# Shell PostgreSQL
docker-compose exec postgres bash

# PostgreSQL client
docker-compose exec postgres psql -U csweb_analytics -d csweb_analytics
```

### Nettoyage

```bash
# Arrêter et supprimer containers
docker-compose down

# Arrêter et supprimer volumes (⚠️ PERTE DE DONNÉES)
docker-compose down -v

# Nettoyer images inutilisées
docker image prune -a

# Nettoyer tout (⚠️ DANGER)
docker system prune -a --volumes
```

---

## Support

- 📧 Email : bounafode@gmail.com
- 💬 GitHub Discussions : https://github.com/BOUNADRAME/csweb-community/discussions
- 🐛 Issues : https://github.com/BOUNADRAME/csweb-community/issues

---

**CSWeb Community Platform - Docker Deployment**

Made with ❤️ by Bouna DRAME
