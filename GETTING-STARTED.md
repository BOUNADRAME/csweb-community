# 🚀 Getting Started - CSWeb Community Platform

> Guide de démarrage rapide pour le projet CSWeb Community

**Projet:** CSWeb Community Platform
**Version:** 1.0-beta
**Date:** Mars 2026

---

## 📋 Table des Matières

1. [Vue d'Ensemble](#vue-densemble)
2. [Prérequis](#prérequis)
3. [Installation Rapide](#installation-rapide)
4. [Première Utilisation](#première-utilisation)
5. [Documentation](#documentation)
6. [Support](#support)

---

## Vue d'Ensemble

**CSWeb Community Platform** simplifie le déploiement et la gestion de CSWeb avec :

✅ **Docker en 1 commande** : `docker-compose up -d`
✅ **Breakout sélectif** : Par dictionnaire (implémenté par Assietou Diagne, ANSD)
✅ **Multi-SGBD** : PostgreSQL, MySQL, SQL Server
✅ **Scheduler Web** : Jobs configurables sans crontab
✅ **Monitoring temps réel** : Logs streaming + métriques
✅ **UI moderne** : Admin Panel React

---

## Prérequis

### Système

- **OS:** Linux, macOS, Windows (avec WSL2)
- **RAM:** 4 GB minimum, 8 GB recommandé
- **Disque:** 10 GB minimum
- **Ports libres:** 8080, 3000, 5432, 3306

### Logiciels

- **Docker:** 24+ ([Installer Docker](https://docs.docker.com/get-docker/))
- **Docker Compose:** 2.x ([Installer Docker Compose](https://docs.docker.com/compose/install/))
- **Git:** 2.x+ ([Installer Git](https://git-scm.com/downloads))

### Vérification

```bash
# Vérifier Docker
docker --version
# Docker version 24.0.0 ou supérieur

# Vérifier Docker Compose
docker-compose --version
# Docker Compose version 2.0.0 ou supérieur

# Vérifier Git
git --version
# git version 2.30.0 ou supérieur
```

---

## Installation Rapide

### Étape 1: Cloner le Projet

```bash
# Cloner le repository
git clone https://github.com/bounadrame/csweb-community.git
cd csweb-community

# OU si vous êtes déjà dans le projet CSWeb 8 PG
cd /Users/bdrame/Developer/opensource/csweb8-pg-oepnsource-contribor/csweb8_pg
```

### Étape 2: Configuration

```bash
# Copier le fichier d'environnement
cp .env.example .env

# Éditer .env avec vos valeurs
# IMPORTANT: Changer les mots de passe et secrets !
nano .env  # ou vim .env ou code .env
```

**Variables OBLIGATOIRES à modifier:**

```bash
# Générer des secrets sécurisés
APP_SECRET=$(openssl rand -hex 32)
JWT_SECRET=$(openssl rand -base64 32)
WEBHOOK_TOKEN=$(openssl rand -base64 32)
MYSQL_PASSWORD=$(openssl rand -base64 24)
POSTGRES_PASSWORD=$(openssl rand -base64 24)
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 24)
```

Ou utilisez ce script:

```bash
# Script pour générer .env avec secrets sécurisés
./scripts/generate-env.sh
```

### Étape 3: Lancer les Services

```bash
# Lancer tous les services Docker
docker-compose up -d

# Voir les logs
docker-compose logs -f

# Attendre que tous les services soient prêts (30-60 secondes)
```

**Services lancés:**

| Service | URL | Credentials |
|---------|-----|-------------|
| CSWeb Core | http://localhost:8080 | `admin` / `admin123` |
| Admin Panel | http://localhost:3000 | `admin` / `admin123` |
| Grafana (opt.) | http://localhost:3001 | `admin` / (voir .env) |
| PostgreSQL | localhost:5432 | (voir .env) |
| MySQL | localhost:3306 | (voir .env) |

### Étape 4: Vérification

```bash
# Vérifier que tous les services sont actifs
docker-compose ps

# Résultat attendu:
# NAME                STATUS              PORTS
# csweb_app           Up                  -
# csweb_nginx         Up                  0.0.0.0:8080->80/tcp
# csweb_mysql         Up                  0.0.0.0:3306->3306/tcp
# csweb_postgres      Up                  0.0.0.0:5432->5432/tcp
# csweb_admin         Up                  0.0.0.0:3000->3000/tcp
# csweb_scheduler     Up                  -

# Tester l'API
curl http://localhost:8080/api/health

# Résultat attendu:
# {"status":"ok","timestamp":"2026-03-14T15:30:00Z"}
```

---

## Première Utilisation

### 1. Login Admin Panel

1. Ouvrir http://localhost:3000 dans votre navigateur
2. Login: `admin` / `admin123`
3. **IMPORTANT:** Changer le mot de passe immédiatement (Settings → Change Password)

### 2. Upload Premier Dictionnaire

**Via Interface Web:**

1. Aller dans **Dictionnaires** → **Upload**
2. Sélectionner votre fichier `.dcf` CSPro
3. Cliquer **Upload**
4. Attendre la fin du traitement

**Via CLI:**

```bash
# Copier le fichier .dcf dans le container
docker cp /path/to/your/dictionary.dcf csweb_app:/tmp/

# Importer via console
docker exec -it csweb_app php bin/console csweb:import-dictionary /tmp/dictionary.dcf
```

### 3. Configurer Breakout PostgreSQL

**Via Interface Web:**

1. Aller dans **Breakout** → **Configuration Schémas**
2. Sélectionner votre dictionnaire
3. Remplir le formulaire:
   - **Type:** PostgreSQL
   - **Host:** `postgres` (nom du service Docker)
   - **Port:** `5432`
   - **Schema:** `public`
   - **User:** `csweb_analytics` (défini dans .env)
   - **Password:** (défini dans .env `POSTGRES_PASSWORD`)
4. Cliquer **Test Connection** (vérifier que ça fonctionne)
5. Cliquer **Save**

**Via API (curl):**

```bash
# 1. Login pour obtenir JWT
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' | jq -r '.accessToken')

# 2. Configurer schéma pour dictionnaire ID=1
curl -X POST http://localhost:8080/api/schemas \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "dictionaryId": 1,
    "dbType": "postgresql",
    "hostName": "postgres",
    "port": 5432,
    "schemaName": "public",
    "schemaUserName": "csweb_analytics",
    "schemaPassword": "your_postgres_password_from_env"
  }'
```

### 4. Premier Breakout

**Via Interface Web:**

1. Aller dans **Breakout** → **Jobs**
2. Trouver votre dictionnaire dans la liste
3. Cliquer **▶ Lancer**
4. Observer le statut en temps réel
5. Quand terminé, vérifier les données dans PostgreSQL

**Via API (curl):**

```bash
# Déclencher breakout pour dictionnaire "EVAL_PRODUCTEURS_USAID"
curl -X POST http://localhost:8080/api/breakout/EVAL_PRODUCTEURS_USAID/trigger \
  -H "Authorization: Bearer $TOKEN"

# Résultat:
# {
#   "success": true,
#   "dictionary": "EVAL_PRODUCTEURS_USAID",
#   "exitCode": 0,
#   "output": "Breakout completed successfully",
#   "durationMs": 4523,
#   "logFile": "EVAL_PRODUCTEURS_USAID_20260314_153045.log"
# }
```

**Via CLI (dans container):**

```bash
# Breakout par dictionnaire (commande Assietou Diagne)
docker exec -it csweb_app php bin/console csweb:process-cases-by-dict EVAL_PRODUCTEURS_USAID
```

### 5. Vérifier les Données dans PostgreSQL

```bash
# Se connecter à PostgreSQL
docker exec -it csweb_postgres psql -U csweb_analytics -d csweb_analytics

# Lister les tables créées
\dt

# Exemple de requête
SELECT COUNT(*) FROM eval_producteurs_usaid_menage;

# Quitter
\q
```

### 6. Configurer Job Automatique (Scheduler)

**Via Interface Web:**

1. Aller dans **Breakout** → **Jobs**
2. Cliquer sur votre dictionnaire → **Modifier**
3. Activer le job: **Enabled** = `true`
4. Configurer le cron: `0 0 1 * * ?` (tous les jours à 1h)
5. Sauvegarder

**Via API:**

```bash
curl -X PATCH http://localhost:8080/api/scheduler/jobs/BREAKOUT_EVAL_PRODUCTEURS_USAID \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "cronExpression": "0 0 1 * * ?",
    "enabled": true
  }'
```

### 7. Monitoring Logs

**Via Interface Web:**

1. Aller dans **Monitoring** → **Logs**
2. Sélectionner filtres (niveau, dictionnaire, search)
3. Activer **Live Streaming** pour voir en temps réel
4. Exporter CSV si besoin

**Via API (SSE Streaming):**

```javascript
// Frontend JavaScript
const eventSource = new EventSource(
  'http://localhost:8080/api/logs/stream?level=ERROR'
);

eventSource.onmessage = (event) => {
  const log = JSON.parse(event.data);
  console.log(`[${log.level}] ${log.message}`);
};
```

---

## Documentation

### Documentation Complète

📁 **[docs/](docs/README.md)** - Documentation exhaustive (200+ pages)

**Documents clés:**

1. **[Plan Stratégique](docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md)** (500+ lignes)
   - Vision et objectifs
   - Architecture complète
   - Stack technique
   - Plan de développement
   - Roadmap v1.0 → v2.5

2. **[Pont Kairos → CSWeb](docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md)** (450+ lignes)
   - Code réutilisable (90% backend)
   - Webhooks PHP (100%)
   - Patterns Scheduler, Logs, API
   - Checklist migration

3. **[Guide Webhooks](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md)** (60 pages)
   - Architecture complète
   - 3 webhooks PHP détaillés
   - Déploiement, sécurité
   - Troubleshooting

4. **[Référence Rapide](docs/api-integration/CSWEB-QUICK-REFERENCE.md)** (10 pages)
   - Commandes curl essentielles
   - Workflow typique
   - Diagnostic rapide

### Tutoriels Vidéo (À venir)

**Playlist YouTube:** CSWeb Community - Guide Complet

1. Installation Docker (10 min)
2. Premier Breakout (8 min)
3. Configuration PostgreSQL (12 min)
4. Scheduler Web UI (15 min)
5. Monitoring et Logs (12 min)

### Commandes Essentielles

```bash
# Démarrer services
docker-compose up -d

# Arrêter services
docker-compose down

# Voir logs
docker-compose logs -f csweb

# Rebuild après modification code
docker-compose up -d --build

# Shell dans container
docker exec -it csweb_app bash

# Breakout par dictionnaire
docker exec -it csweb_app php bin/console csweb:process-cases-by-dict <DICT>

# Sync dictionnaires en jobs
docker exec -it csweb_app php bin/console csweb:breakout:sync

# Backup MySQL
docker exec csweb_mysql mysqldump -u root -p<password> csweb_metadata > backup.sql

# Backup PostgreSQL
docker exec csweb_postgres pg_dump -U csweb_analytics csweb_analytics > backup.sql

# Restore MySQL
cat backup.sql | docker exec -i csweb_mysql mysql -u root -p<password> csweb_metadata

# Restore PostgreSQL
cat backup.sql | docker exec -i csweb_postgres psql -U csweb_analytics csweb_analytics
```

---

## Support

### Documentation

- 📖 **Guides Complets:** [docs/](docs/README.md)
- ⚡ **Référence Rapide:** [docs/api-integration/CSWEB-QUICK-REFERENCE.md](docs/api-integration/CSWEB-QUICK-REFERENCE.md)
- 📋 **FAQ:** [docs/FAQ.md](docs/FAQ.md) (à venir)

### Communauté

- 💬 **Discord:** https://discord.gg/csweb-community (à venir)
- 💡 **GitHub Discussions:** https://github.com/bounadrame/csweb-community/discussions (à venir)
- 🐛 **GitHub Issues:** https://github.com/bounadrame/csweb-community/issues (à venir)

### Contact Direct

- 📧 **Email:** bdrame@statinfo.sn
- 🐦 **Twitter:** @CSWebCommunity (à venir)
- 💼 **LinkedIn:** CSWeb Community (à venir)

### Problèmes Courants

#### Port 8080 déjà utilisé

```bash
# Changer le port dans docker-compose.yml
ports:
  - "8081:80"  # Au lieu de 8080:80
```

#### Erreur "Cannot connect to PostgreSQL"

```bash
# Vérifier que PostgreSQL est démarré
docker-compose ps csweb_postgres

# Voir les logs PostgreSQL
docker-compose logs csweb_postgres

# Recréer le container
docker-compose down
docker-compose up -d
```

#### Breakout échoue avec "Permission denied"

```bash
# Donner les permissions au dossier var/
docker exec -it csweb_app chown -R www-data:www-data /var/www/csweb/var
```

#### Logs ne s'affichent pas

```bash
# Vérifier le fichier de log
docker exec -it csweb_app tail -f /var/www/csweb/var/logs/app.log

# Vérifier les permissions
docker exec -it csweb_app ls -la /var/www/csweb/var/logs/
```

---

## Prochaines Étapes

### Pour les Utilisateurs

1. ✅ Upload de vos dictionnaires CSPro
2. ✅ Configuration breakout PostgreSQL
3. ✅ Test premier breakout manuel
4. ✅ Configuration jobs automatiques (scheduler)
5. ✅ Exploration monitoring et logs

### Pour les Développeurs

1. ✅ Lire [docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md](docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md)
2. ✅ Setup environnement de développement
3. ✅ Comprendre l'architecture (docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md)
4. ✅ Contribuer (voir CONTRIBUTING.md)

### Pour les Administrateurs

1. ✅ Lire [docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md)
2. ✅ Configuration production (SSL, backup, monitoring)
3. ✅ Déploiement sur serveur (voir docs/DEPLOYMENT-GUIDE.md - à venir)
4. ✅ Formation équipe

---

## Ressources

**Projet:**
- 🏠 **Site:** https://bounadrame.github.io/csweb-community/ (à venir)
- 💻 **GitHub:** https://github.com/bounadrame/csweb-community (à venir)
- 📺 **YouTube:** https://youtube.com/@CSWebCommunity (à venir)

**CSPro/CSWeb:**
- 📖 **CSPro Docs:** https://www.census.gov/data/software/cspro.html
- 🌐 **CSWeb Docs:** https://www.csprousers.org/help/CSWeb/
- 💬 **CSPro Forum:** https://www.csprousers.org/forum/

**Inspiration:**
- 🐳 **Docker CSWeb:** https://github.com/csprousers/docker-csweb
- 🚀 **Portfolio:** https://bounadrame.github.io/portfolio/

---

## Contributeurs

- **Bouna DRAME** - Lead Developer, Documentation
- **Assietou Diagne (ANSD)** - Breakout sélectif, PostgreSQL support

Voir [CONTRIBUTORS.md](CONTRIBUTORS.md) pour la liste complète.

---

## License

MIT License - voir [LICENSE](LICENSE) pour les détails.

---

**Made with ❤️ by the CSWeb Community**

_Simplifying statistical data collection for Africa and beyond_

---

<div align="center">

**[⬆ Retour en haut](#-getting-started---csweb-community-platform)**

**Questions ? Ouvrez une [issue](https://github.com/bounadrame/csweb-community/issues) !**

</div>
