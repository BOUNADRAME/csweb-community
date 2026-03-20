# Performance Tuning - CSWeb Community Platform

Guide complet pour le dimensionnement et l'optimisation de CSWeb en production.

## Architecture

CSWeb utilise un mécanisme de **templates envsubst** pour piloter la configuration
depuis le fichier `.env` :

1. Les fichiers `docker/php/php.ini`, `docker/apache/000-default.conf` et
   `docker/apache/mpm_prefork.conf` contiennent des variables `${VAR}`
2. Au démarrage du container `csweb`, `docker-entrypoint.sh` exécute `envsubst`
   pour générer les fichiers de config finaux
3. MySQL et PostgreSQL reçoivent leurs paramètres via `command:` args dans
   `docker-compose.yml`

**Modifier `.env` + `docker-compose up -d` suffit pour appliquer les changements.**

---

## Variables d'environnement

### PHP

| Variable | Default | Description |
|----------|---------|-------------|
| `PHP_MEMORY_LIMIT` | `512M` | Mémoire max par processus PHP |
| `PHP_MAX_EXECUTION_TIME` | `300` | Temps max d'exécution (secondes) |
| `PHP_MAX_INPUT_TIME` | `300` | Temps max de parsing input |
| `PHP_UPLOAD_MAX_FILESIZE` | `100M` | Taille max d'un fichier uploadé |
| `PHP_POST_MAX_SIZE` | `100M` | Taille max d'une requête POST |
| `PHP_SESSION_GC_MAXLIFETIME` | `7200` | Durée de session (secondes) |
| `PHP_OPCACHE_MEMORY` | `128` | Mémoire OPcache (MB) |
| `PHP_OPCACHE_MAX_FILES` | `10000` | Nombre max de fichiers en cache |

### MySQL Metadata

| Variable | Default | Description |
|----------|---------|-------------|
| `MYSQL_MAX_CONNECTIONS` | `200` | Connexions simultanées max |
| `MYSQL_INNODB_BUFFER_POOL_SIZE` | `256M` | Cache InnoDB (75% RAM dispo idéal) |
| `MYSQL_INNODB_LOG_FILE_SIZE` | `64M` | Taille du journal InnoDB |
| `MYSQL_SLOW_QUERY_TIME` | `2` | Seuil requête lente (secondes) |
| `MYSQL_MAX_ALLOWED_PACKET` | `64M` | Taille max d'un paquet |
| `MYSQL_THREAD_CACHE_SIZE` | `16` | Threads en cache |
| `MYSQL_TABLE_OPEN_CACHE` | `2000` | Tables ouvertes en cache |
| `MYSQL_WAIT_TIMEOUT` | `28800` | Timeout connexion inactive (sec) |

### PostgreSQL Breakout

| Variable | Default | Description |
|----------|---------|-------------|
| `PG_MAX_CONNECTIONS` | `200` | Connexions simultanées max |
| `PG_SHARED_BUFFERS` | `256MB` | Cache partagé (25% RAM dispo idéal) |
| `PG_EFFECTIVE_CACHE_SIZE` | `1GB` | Estimation cache OS (50-75% RAM) |
| `PG_WORK_MEM` | `4MB` | Mémoire par opération de tri |
| `PG_MAINTENANCE_WORK_MEM` | `64MB` | Mémoire pour VACUUM, CREATE INDEX |

### Apache MPM Prefork

| Variable | Default | Description |
|----------|---------|-------------|
| `APACHE_MAX_REQUEST_WORKERS` | `150` | Processus Apache max (= users simultanés) |
| `APACHE_SERVER_LIMIT` | `150` | Limite serveur (doit être >= MaxRequestWorkers) |
| `APACHE_KEEP_ALIVE_TIMEOUT` | `5` | Timeout keep-alive (secondes) |
| `APACHE_MAX_KEEP_ALIVE_REQUESTS` | `100` | Requêtes max par connexion keep-alive |
| `APACHE_TIMEOUT` | `300` | Timeout requête (secondes) |

---

## Profils de dimensionnement

### Profil Dev (1-5 utilisateurs, laptop)

```env
PHP_MEMORY_LIMIT=256M
PHP_MAX_EXECUTION_TIME=120
PHP_OPCACHE_MEMORY=64
PHP_OPCACHE_MAX_FILES=4000

MYSQL_MAX_CONNECTIONS=50
MYSQL_INNODB_BUFFER_POOL_SIZE=128M

PG_MAX_CONNECTIONS=50
PG_SHARED_BUFFERS=128MB
PG_EFFECTIVE_CACHE_SIZE=512MB

APACHE_MAX_REQUEST_WORKERS=25
APACHE_SERVER_LIMIT=25
```

### Profil Production Standard (20-50 utilisateurs, 4GB RAM)

```env
PHP_MEMORY_LIMIT=512M
PHP_MAX_EXECUTION_TIME=300
PHP_OPCACHE_MEMORY=128
PHP_OPCACHE_MAX_FILES=10000

MYSQL_MAX_CONNECTIONS=200
MYSQL_INNODB_BUFFER_POOL_SIZE=512M

PG_MAX_CONNECTIONS=200
PG_SHARED_BUFFERS=512MB
PG_EFFECTIVE_CACHE_SIZE=2GB
PG_WORK_MEM=8MB

APACHE_MAX_REQUEST_WORKERS=150
APACHE_SERVER_LIMIT=150
```

### Profil Haute Charge (200+ utilisateurs, 16GB RAM)

```env
PHP_MEMORY_LIMIT=1G
PHP_MAX_EXECUTION_TIME=600
PHP_OPCACHE_MEMORY=256
PHP_OPCACHE_MAX_FILES=20000

MYSQL_MAX_CONNECTIONS=500
MYSQL_INNODB_BUFFER_POOL_SIZE=4G
MYSQL_INNODB_LOG_FILE_SIZE=256M
MYSQL_THREAD_CACHE_SIZE=64
MYSQL_TABLE_OPEN_CACHE=4000

PG_MAX_CONNECTIONS=500
PG_SHARED_BUFFERS=4GB
PG_EFFECTIVE_CACHE_SIZE=12GB
PG_WORK_MEM=16MB
PG_MAINTENANCE_WORK_MEM=512MB

APACHE_MAX_REQUEST_WORKERS=400
APACHE_SERVER_LIMIT=400
APACHE_KEEP_ALIVE_TIMEOUT=3
APACHE_MAX_KEEP_ALIVE_REQUESTS=200
```

---

## Formules de dimensionnement

### Mémoire totale requise (estimation)

```
RAM_TOTALE >= (APACHE_MAX_REQUEST_WORKERS * PHP_MEMORY_LIMIT)
           + MYSQL_INNODB_BUFFER_POOL_SIZE
           + PG_SHARED_BUFFERS
           + 1GB (OS + overhead)
```

**Exemple** pour 150 workers avec 512M :
```
150 * 512MB + 512MB + 512MB + 1GB = ~78GB (théorique max)
```

En pratique, les processus PHP utilisent rarement le max. Compter ~50-100MB
en moyenne par worker, soit :

```
150 * 100MB + 512MB + 512MB + 1GB = ~17GB (réaliste)
```

### Connexions base de données

```
MYSQL_MAX_CONNECTIONS >= APACHE_MAX_REQUEST_WORKERS + 20 (marge admin/cron)
PG_MAX_CONNECTIONS    >= APACHE_MAX_REQUEST_WORKERS + 20
```

### Apache ServerLimit

```
APACHE_SERVER_LIMIT >= APACHE_MAX_REQUEST_WORKERS (obligatoire)
```

---

## Commande de vérification

```bash
# Afficher toute la configuration
docker exec csweb-app php bin/console csweb:check-config

# Avec test des connexions
docker exec csweb-app php bin/console csweb:check-config --test-connections

# Sortie JSON (pour monitoring)
docker exec csweb-app php bin/console csweb:check-config --json
```

---

## Workflow de modification

1. Modifier les variables dans `.env`
2. Recréer les containers : `docker-compose up -d`
3. Vérifier : `docker exec csweb-app php bin/console csweb:check-config`
4. Pour PHP uniquement, vérifier avec : `docker exec csweb-app php -i | grep memory_limit`
