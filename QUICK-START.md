---
layout: default
title: Quick Start
---

# 🚀 CSWeb Community Platform - Quick Start

> **Démarrez CSWeb avec breakout PostgreSQL en 5 minutes**

**Version :** 1.0.0
**Date :** 14 Mars 2026
**Auteur :** Bouna DRAME

---

## ⚡ Installation Express (5 minutes)

### Prérequis

- Docker & Docker Compose installés
- 4 GB RAM minimum
- 10 GB espace disque

### 1. Cloner le Repository

```bash
git clone https://github.com/BOUNADRAME/pg_csweb8_latest_2026.git
cd pg_csweb8_latest_2026
```

### 2. Lancer l'Installation Automatique

```bash
chmod +x install.sh
./install.sh
```

Le script va :
- ✅ Vérifier Docker/Docker Compose
- ✅ Créer fichier `.env` avec mots de passe sécurisés
- ✅ Télécharger images Docker
- ✅ Démarrer MySQL + PostgreSQL + CSWeb
- ✅ Afficher les URLs d'accès

### 3. Accéder au Setup

**URL :** http://localhost:8080/setup/

**Remplir le formulaire :**

| Champ | Valeur |
|-------|--------|
| Database Name | `csweb_metadata` |
| Hostname | `mysql` |
| Database Username | `csweb_user` |
| Database Password | *(voir .env)* |
| Administrative Password | `admin123` (changez-le) |
| Files Directory | `/var/www/html/files` |
| API URL | `http://localhost:8080/api/` |
| Timezone | `Africa/Dakar` (ou votre timezone) |

**Cliquer "Submit"** → CSWeb créera automatiquement la configuration.

### 4. Se Connecter

**URL :** http://localhost:8080

**Credentials :**
- Username: `admin`
- Password: `admin123` (celui défini au setup)

---

## 🎯 Premier Breakout PostgreSQL

### 1. Vérifier les Drivers

```bash
docker-compose exec csweb php bin/console csweb:check-database-drivers
```

**Résultat attendu :**
```
Database Drivers:
POSTGRESQL: ✅ Available
MYSQL: ✅ Available
```

### 2. Synchroniser un Dictionnaire CSPro

1. **Uploader votre dictionnaire** via l'interface CSWeb
2. **Synchroniser des données** depuis devices CSPro

### 3. Lancer le Breakout

```bash
docker-compose exec csweb php bin/console csweb:process-cases-by-dict dictionnaires=VOTRE_DICT
```

**Exemple :**
```bash
docker-compose exec csweb php bin/console csweb:process-cases-by-dict dictionnaires=SURVEY_DICT
```

### 4. Vérifier les Tables PostgreSQL

```bash
# Accéder à PostgreSQL
docker-compose exec postgres psql -U csweb_analytics -d csweb_analytics

# Lister les tables
\dt

# Vous verrez:
# survey_cases
# survey_level_1
# survey_level_2
# survey_record_001
```

---

## 📊 Outils de Développement

### phpMyAdmin (MySQL)

**URL :** http://localhost:8081

**Credentials :**
- Server: `mysql`
- Username: `root`
- Password: *(voir .env - MYSQL_ROOT_PASSWORD)*

### pgAdmin (PostgreSQL)

**URL :** http://localhost:8082

**Credentials :**
- Email: `admin@csweb.local`
- Password: `admin123`

**Ajouter serveur :**
1. Clic droit "Servers" → "Create" → "Server"
2. **General tab** :
   - Name: `CSWeb Analytics`
3. **Connection tab** :
   - Host: `postgres`
   - Port: `5432`
   - Database: `csweb_analytics`
   - Username: `csweb_analytics`
   - Password: *(voir .env - POSTGRES_PASSWORD)*

---

## 🛠️ Commandes Utiles

### Docker Compose

```bash
# Voir les logs
docker-compose logs -f csweb

# Arrêter les services
docker-compose down

# Redémarrer
docker-compose restart

# Vérifier le statut
docker-compose ps

# Accéder au shell CSWeb
docker-compose exec csweb bash

# Accéder à MySQL
docker-compose exec mysql mysql -u root -p

# Accéder à PostgreSQL
docker-compose exec postgres psql -U csweb_analytics -d csweb_analytics
```

### Console CSWeb

```bash
# Vérifier drivers disponibles
docker-compose exec csweb php bin/console csweb:check-database-drivers

# Tester connexions
docker-compose exec csweb php bin/console csweb:check-database-drivers --test-connections

# Lister dictionnaires
docker-compose exec csweb php bin/console cspro:list-dictionaries

# Breakout sélectif
docker-compose exec csweb php bin/console csweb:process-cases-by-dict dictionnaires=DICT_NAME

# Breakout multiples dictionnaires
docker-compose exec csweb php bin/console csweb:process-cases-by-dict dictionnaires=DICT1,DICT2,DICT3
```

---

## 🔧 Configuration Avancée

### Changer le Type de Base de Données

Éditer `.env` :

```bash
# Pour utiliser MySQL au lieu de PostgreSQL
DEFAULT_BREAKOUT_DB_TYPE=mysql

# Pour utiliser SQL Server (nécessite configuration supplémentaire)
DEFAULT_BREAKOUT_DB_TYPE=sqlserver
```

Puis redémarrer :

```bash
docker-compose restart csweb
```

### Ajouter SQL Server

1. Éditer `.env` :

```bash
SQLSERVER_HOST=sqlserver
SQLSERVER_PORT=1433
SQLSERVER_DATABASE=csweb_analytics
SQLSERVER_USER=sa
SQLSERVER_PASSWORD=YourStrong!Passw0rd
```

2. Ajouter service dans `docker-compose.yml` :

```yaml
  sqlserver:
    image: mcr.microsoft.com/mssql/server:2022-latest
    environment:
      - ACCEPT_EULA=Y
      - SA_PASSWORD=YourStrong!Passw0rd
    ports:
      - "1433:1433"
```

3. Redémarrer :

```bash
docker-compose up -d
```

---

## 📚 Documentation Complète

| Guide | Description | Lien |
|-------|-------------|------|
| **Installation Vanilla** | Installation CSWeb standard | [docs/INSTALLATION-CSWEB-VANILLA.md](docs/INSTALLATION-CSWEB-VANILLA.md) |
| **Migration Breakout** | Transformations BEFORE/AFTER | [docs/MIGRATION-BREAKOUT-SELECTIF.md](docs/MIGRATION-BREAKOUT-SELECTIF.md) |
| **Configuration Multi-DB** | PostgreSQL/MySQL/SQL Server | [docs/CONFIGURATION-MULTI-DATABASE.md](docs/CONFIGURATION-MULTI-DATABASE.md) |
| **Notes Configuration** | MySQL CSWeb vs Breakout | [docs/NOTES-CONFIGURATION-CSWEB.md](docs/NOTES-CONFIGURATION-CSWEB.md) |

---

## ❓ FAQ Rapide

### Q: PostgreSQL ou MySQL pour le breakout ?

**R:** **PostgreSQL recommandé** pour :
- Meilleures performances analytics
- Support JSON natif
- Conformité SQL supérieure

MySQL fonctionne mais moins optimisé pour analytics.

### Q: Puis-je utiliser plusieurs bases ?

**R:** Oui ! Exemple :
- Dictionnaire `SURVEY` → PostgreSQL
- Dictionnaire `CENSUS` → MySQL

Configuration via `.env` ou API.

### Q: Comment migrer de Vanilla vers Community ?

**R:** Suivre [docs/MIGRATION-BREAKOUT-SELECTIF.md](docs/MIGRATION-BREAKOUT-SELECTIF.md) (~3-4 heures).

### Q: Breakout échoue, que faire ?

**R:**

1. Vérifier drivers :
```bash
docker-compose exec csweb php bin/console csweb:check-database-drivers
```

2. Vérifier connexions :
```bash
docker-compose exec csweb php bin/console csweb:check-database-drivers --test-connections
```

3. Voir logs :
```bash
docker-compose logs -f csweb
```

### Q: Comment sauvegarder mes données ?

**R:**

```bash
# MySQL
docker-compose exec mysql mysqldump -u root -p csweb_metadata > backup_mysql.sql

# PostgreSQL
docker-compose exec postgres pg_dump -U csweb_analytics csweb_analytics > backup_postgres.sql
```

---

## 🚨 Troubleshooting

### Erreur "Port 8080 already in use"

Changer le port dans `.env` :

```bash
CSWEB_PORT=9080
```

Redémarrer :

```bash
docker-compose down
docker-compose up -d
```

### Erreur "Cannot connect to MySQL"

Vérifier que MySQL est démarré :

```bash
docker-compose ps mysql
```

Si arrêté :

```bash
docker-compose up -d mysql
```

### Erreur "PostgreSQL connection refused"

Vérifier PostgreSQL :

```bash
docker-compose ps postgres
docker-compose logs postgres
```

Redémarrer si nécessaire :

```bash
docker-compose restart postgres
```

---

## 📞 Support

- 📧 Email : bounafode@gmail.com
- 💬 GitHub Discussions : https://github.com/BOUNADRAME/pg_csweb8_latest_2026/discussions
- 🐛 Issues : https://github.com/BOUNADRAME/pg_csweb8_latest_2026/issues
- 📖 Documentation : [docs/](docs/)

---

## ✅ Checklist de Démarrage

- [ ] Docker & Docker Compose installés
- [ ] Repository cloné
- [ ] `./install.sh` exécuté avec succès
- [ ] Setup CSWeb complété (http://localhost:8080/setup/)
- [ ] Connexion admin réussie
- [ ] Drivers vérifiés (`csweb:check-database-drivers`)
- [ ] Premier dictionnaire synchronisé
- [ ] Premier breakout réussi
- [ ] Tables PostgreSQL vérifiées

---

**CSWeb Community Platform - Démocratiser CSWeb pour l'Afrique**

Made with ❤️ by Bouna DRAME
