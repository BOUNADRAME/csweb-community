# Changelog - CSWeb Community Platform

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

---

## [2.0.0] - 2026-03-14

### 🚀 Architecture Flexible - LOCAL/REMOTE • Multi-SGBD • Migration à Chaud

**Release Majeure** : Support complet pour déploiements locaux ET distants avec 3 SGBD (PostgreSQL, MySQL, SQL Server).

#### 🌐 Architecture Flexible (BREAKING CHANGES)

**Concepts Clés:**
- ✅ **Mode LOCAL** : Docker containers pour DB breakout (développement, test)
- ✅ **Mode REMOTE** : Serveur distant pour breakout (production, RGPH5)
- ✅ **2 Bases Distinctes** :
  - MySQL métadonnées (toujours local, créé par setup.php)
  - DB Breakout (local OU distant, configurable)
- ✅ **Migration à chaud** : Changement mode/type sans rebuild images
- ✅ **RGPH5 Sénégal** : Support natif (2 serveurs séparés)

**Variables d'environnement (BREAKING CHANGES):**
```bash
# NOUVEAU v2.0
BREAKOUT_MODE=local          # local | remote
BREAKOUT_DB_TYPE=postgresql  # postgresql | mysql | sqlserver

# PostgreSQL Breakout
POSTGRES_HOST=postgres       # 'postgres' if local, IP if remote
POSTGRES_PORT=5432
POSTGRES_DATABASE=csweb_analytics
POSTGRES_USER=csweb_analytics
POSTGRES_PASSWORD=SecurePass123

# MySQL Breakout (NOUVEAU)
MYSQL_BREAKOUT_HOST=mysql-breakout
MYSQL_BREAKOUT_PORT=3307
MYSQL_BREAKOUT_DATABASE=csweb_breakout
MYSQL_BREAKOUT_USER=breakout_user
MYSQL_BREAKOUT_PASSWORD=SecurePass123

# SQL Server Breakout (NOUVEAU)
SQLSERVER_HOST=sqlserver
SQLSERVER_PORT=1433
SQLSERVER_DATABASE=CSWeb_Analytics
SQLSERVER_USER=sa
SQLSERVER_PASSWORD=YourStrong!Passw0rd
```

#### 🐳 Docker & Installation

**Dockerfile (modifié):**
- ✅ Installation de TOUS les drivers : pdo_mysql, pdo_pgsql, sqlsrv, pdo_sqlsrv
- ✅ Microsoft ODBC Driver 18 pour SQL Server
- ✅ Extensões PECL : sqlsrv-5.11.1, pdo_sqlsrv-5.11.1
- ✅ Image unique supportant 3 SGBD (pas de rebuild nécessaire)

**docker-compose.yml (réécriture complète - 295 lignes):**
- ✅ **Profiles dynamiques** :
  - `local-postgres` : CSWeb + MySQL metadata + PostgreSQL breakout
  - `local-mysql` : CSWeb + MySQL metadata + MySQL breakout
  - `local-sqlserver` : CSWeb + MySQL metadata + SQL Server breakout
- ✅ **Mode remote** : Seulement CSWeb + MySQL metadata (pas de containers breakout)
- ✅ **Lancement conditionnel** :
  ```bash
  # Local PostgreSQL
  docker-compose --profile local-postgres up -d

  # Remote SQL Server
  docker-compose up -d csweb mysql  # Breakout sur serveur distant
  ```
- ✅ **Health checks** automatiques sur tous les services
- ✅ **Volumes persistants** pour data, files, logs

**install.sh (réécriture complète - 450+ lignes):**
- ✅ **Wizard interactif en 3 étapes** :
  - Étape 1 : Mode déploiement (Local/Remote)
  - Étape 2 : Type base de données (PostgreSQL/MySQL/SQL Server)
  - Étape 3 : Configuration remote (si mode remote choisi)
- ✅ **Génération automatique** du fichier `.env`
- ✅ **Mots de passe sécurisés** générés automatiquement
- ✅ **Détection Docker/Docker Compose**
- ✅ **Lancement automatique** avec bon profile
- ✅ **Affichage URLs** d'accès (CSWeb, phpMyAdmin, pgAdmin)
- ✅ **Interface colorée** avec emojis et indicateurs de progression

#### 📚 Documentation

**Nouveaux Documents:**
- ✅ `docs/ARCHITECTURE-FLEXIBLE.md` (600+ lignes, 40 pages)
  - Concepts LOCAL vs REMOTE
  - Support 3 SGBD (PostgreSQL, MySQL, SQL Server)
  - Scénarios réels (RGPH5 Sénégal, Institut National Statistique)
  - Migration à chaud (local → remote sans perte données)
  - Configuration avancée
  - Troubleshooting complet

**Documents Mis à Jour:**
- ✅ `index.md` - Refonte complète pour v2.0 (landing page)
  - Architecture AVANT/APRÈS
  - 3 cas d'usage v2.0
  - Stack technique mis à jour
- ✅ `QUICK-START.md` - Installation interactive en 5 minutes
  - Wizard 3 étapes
  - Premier breakout PostgreSQL
  - Vérification drivers
  - Troubleshooting v2.0
- ✅ `.env.example` - Nouvelles variables (120 lignes)
  - BREAKOUT_MODE, BREAKOUT_DB_TYPE
  - Configuration 3 SGBD
  - Documentation inline complète

**GitHub Pages:**
- ✅ Configuration Jekyll avec thème Cayman
- ✅ YAML front matter sur tous les .md
- ✅ Navigation relative (.html links)
- ✅ Workflow automatique (Node 22, timeout 5 min)

#### 🎯 Cas d'Usage v2.0

**1. Développeur Local (Mode LOCAL):**
```bash
./install.sh
# Choisir: 1) Local, 1) PostgreSQL
# Résultat: Tout en Docker (CSWeb + MySQL + PostgreSQL)
```

**2. RGPH5 Sénégal (Mode REMOTE):**
```bash
# Architecture: 2 serveurs physiques séparés
# - Serveur 1 (172.16.0.10): CSWeb + MySQL metadata
# - Serveur 2 (172.16.0.50): SQL Server 2022 breakout

./install.sh
# Choisir: 2) Remote, 3) SQL Server
# Host: 172.16.0.50, Port: 1433
# Résultat: CSWeb connecté à SQL Server distant
```

**3. Institut National Statistique:**
```bash
# Breakout simultané de 3 enquêtes
docker-compose exec csweb php bin/console csweb:process-cases-by-dict \
  dictionnaires=RGPH_DICT,EDS_DICT,ENES_DICT

# Résultat:
# - rgph_cases (Recensement)
# - eds_cases (Enquête Démographique)
# - enes_cases (Enquête Emploi)
```

**4. Migration Progressive:**
```bash
# Scénario: Tester PostgreSQL sur un dictionnaire pilote
# - Dictionnaire PILOT → PostgreSQL (test)
# - Autres dictionnaires → MySQL (existant)
# Migration à chaud sans arrêt service
```

#### ⚙️ Fonctionnalités Techniques

**Support Multi-SGBD:**
- ✅ PostgreSQL 16 (recommandé pour analytics, JSON natif)
- ✅ MySQL 8.0 (compatible, performant)
- ✅ SQL Server 2022 (enterprise, RGPH5)
- ✅ Hot-swap : Changement DB via .env + restart (pas rebuild)

**Breakout Sélectif (conservé de v1.0):**
- ✅ Isolation complète par dictionnaire
- ✅ Multi-threading (3 threads, 500 cases chacun)
- ✅ Label-based naming (`{label}_cases`, `{label}_level_1`)
- ✅ Traçabilité claire

**Outils de Développement:**
- ✅ phpMyAdmin (port 8081) pour MySQL metadata
- ✅ pgAdmin (port 8082) pour PostgreSQL breakout
- ✅ Scripts de vérification : `csweb:check-database-drivers`

#### 🔧 Commandes v2.0

**Vérification:**
```bash
# Vérifier drivers disponibles
docker-compose exec csweb php bin/console csweb:check-database-drivers
# Résultat:
# POSTGRESQL: ✅ Available
# MYSQL: ✅ Available
# SQLSERVER: ✅ Available (si configuré)

# Tester connexions
docker-compose exec csweb php bin/console csweb:check-database-drivers --test-connections
```

**Démarrage:**
```bash
# Local PostgreSQL
docker-compose --profile local-postgres up -d

# Local MySQL
docker-compose --profile local-mysql up -d

# Local SQL Server
docker-compose --profile local-sqlserver up -d

# Remote (any DB type)
docker-compose up -d csweb mysql
```

**Breakout:**
```bash
# Breakout d'un dictionnaire
docker-compose exec csweb php bin/console csweb:process-cases-by-dict \
  dictionnaires=SURVEY_DICT

# Breakout de plusieurs dictionnaires
docker-compose exec csweb php bin/console csweb:process-cases-by-dict \
  dictionnaires=DICT1,DICT2,DICT3
```

#### 🚨 Breaking Changes

**1. Variables d'environnement:**
- **AJOUTÉ** : `BREAKOUT_MODE` (local/remote)
- **AJOUTÉ** : `BREAKOUT_DB_TYPE` (postgresql/mysql/sqlserver)
- **RENOMMÉ** : `POSTGRES_*` devient spécifique au breakout
- **AJOUTÉ** : `MYSQL_BREAKOUT_*` (nouvelle instance MySQL pour breakout)
- **AJOUTÉ** : `SQLSERVER_*` (support SQL Server)

**2. docker-compose.yml:**
- **MODIFIÉ** : Utilisation de profiles (au lieu de services toujours actifs)
- **AJOUTÉ** : Service `mysql-breakout` (port 3307)
- **AJOUTÉ** : Service `sqlserver` (optionnel)
- **MODIFIÉ** : Lancement via `--profile` flag

**3. Migration v1.0 → v2.0:**
```bash
# Sauvegarder données existantes
docker-compose exec postgres pg_dump -U csweb_analytics csweb_analytics > backup.sql

# Mettre à jour .env (ajouter nouvelles variables)
BREAKOUT_MODE=local
BREAKOUT_DB_TYPE=postgresql
# (conserver autres variables)

# Redémarrer avec nouveau profile
docker-compose down
docker-compose --profile local-postgres up -d

# Restaurer données
docker-compose exec postgres psql -U csweb_analytics csweb_analytics < backup.sql
```

#### 📊 Statistiques v2.0

**Code:**
- Dockerfile : +50 lignes (drivers SQL Server)
- docker-compose.yml : Réécriture complète (295 lignes, +150 lignes)
- install.sh : Réécriture complète (450 lignes, +300 lignes)
- .env.example : +40 lignes (nouvelles variables)

**Documentation:**
- `docs/ARCHITECTURE-FLEXIBLE.md` : +600 lignes (nouveau)
- `index.md` : +100 lignes (refonte v2.0)
- `QUICK-START.md` : +50 lignes (wizard, troubleshooting)
- **Total : +800 lignes documentation**

**Temps Développement:**
- Analyse architecture : 1h
- Dockerfile + docker-compose : 2h
- install.sh (wizard) : 3h
- Documentation : 4h
- Tests : 2h
- **Total : 12h**

#### 🤝 Contributeurs

- **Bouna DRAME** - Lead Developer, Architecture Flexible v2.0
- **Assietou Diagne (ANSD)** - Breakout sélectif (v1.0, conservé)

---

## [1.0.0] - 2026-03-14

### 🎉 Première Release - Breakout Sélectif + Documentation Complète

#### Ajouté

##### Documentation Principale (11 documents, ~7750 lignes, ~255 pages)

**Fichiers Racine:**
- ✅ `README-COMMUNITY.md` - README projet communautaire (18 KB)
- ✅ `GETTING-STARTED.md` - Guide démarrage rapide (13 KB)
- ✅ `DOCUMENTATION-INDEX.md` - Index navigation complète (14 KB)
- ✅ `.env.example` - Variables d'environnement complètes (12 KB)
- ✅ `CHANGELOG.md` - Ce fichier (changelog)

**Documentation Stratégique (docs/):**
- ✅ `docs/README.md` - Index documentation (10 KB)
- ✅ `docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md` - Plan stratégique complet (62 KB, 60 pages)
  - Vision et objectifs (court/moyen/long terme)
  - État des lieux (CSWeb 8 PG + Kairos API)
  - Architecture proposée (diagrammes, composants)
  - Fonctionnalités clés (breakout, scheduler, logs, UI)
  - Stack technique détaillée
  - Plan de développement (8 semaines, 6 phases)
  - Roadmap v1.0 → v2.5
  - Exemples de code complets (Docker, API, Scheduler)

- ✅ `docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md` - Pont Kairos → CSWeb (32 KB, 50 pages)
  - Cartographie complète de la réutilisation du code Kairos
  - Webhooks PHP : 100% réutilisables
  - API REST : 90% portage Java → PHP
  - Scheduler : Pattern complet réutilisable
  - Logs Parsing : Regex + code 100% portables
  - Tests : Exemples PHPUnit portés depuis JUnit
  - Checklist migration complète (5 phases)
  - **Gains estimés : 63% temps économisé** (4.5 semaines vs 12)

**Documentation Webhooks (docs/api-integration/):**
- ✅ `docs/api-integration/INDEX.md` - Navigation docs webhooks (12 KB)
- ✅ `docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md` - Guide complet (36 KB, 60 pages)
  - Architecture globale (Frontend → Kairos → CSWeb)
  - Les 3 webhooks PHP détaillés
  - Déploiement serveur CSWeb
  - Sécurité et authentification (Bearer Token + JWT)
  - Monitoring et logs (parsing Symfony)
  - Troubleshooting complet
  - 20+ exemples d'utilisation (curl, JavaScript, bash)

- ✅ `docs/api-integration/CSWEB-QUICK-REFERENCE.md` - Référence rapide (11 KB, 10 pages)
  - Commandes curl essentielles
  - Authentification (JWT + Bearer Token)
  - Gestion dictionnaires, breakout, scheduler, logs
  - Diagnostic et maintenance
  - Workflow typique complet

- ✅ `docs/api-integration/api-cspro-breakout.md` - API Frontend (6 KB, 10 pages)
  - Référence API complète
  - Endpoints REST Kairos
  - Formats JSON
  - Workflow intégration

- ✅ `docs/api-integration/csweb-webhook/README.md` - Scripts PHP (7.5 KB)
  - Documentation des 3 scripts PHP
  - Instructions d'installation
  - Configuration Apache
  - Tests de validation

**Scripts PHP Webhooks (3 fichiers):**
- ✅ `breakout-webhook.php` - Webhook breakout CSPro (5 KB)
- ✅ `log-reader-webhook.php` - Webhook lecture logs (4.5 KB)
- ✅ `dictionary-schema-webhook.php` - Webhook gestion schémas (8.4 KB)

##### Fonctionnalités

**Breakout Sélectif (par Assietou Diagne, ANSD):**
- ✅ Commande Symfony : `php bin/console csweb:process-cases-by-dict <DICT>`
- ✅ Breakout par dictionnaire spécifique (au lieu de tous)
- ✅ Support PostgreSQL + MySQL

**Fichiers Modifiés (Assietou Diagne):**
- ✅ `src/AppBundle/CSPro/DictionarySchemaHelper.php` - Nettoyage sélectif tables
- ✅ `src/AppBundle/Service/DataSettings.php` - Support PostgreSQL
- ✅ `src/AppBundle/Repository/MapDataRepository.php` - Requêtes adaptées

**Configuration:**
- ✅ Variables d'environnement complètes (`.env.example`)
  - Général (APP_ENV, APP_SECRET, JWT_SECRET)
  - MySQL (metadata CSWeb)
  - PostgreSQL (breakout analytics)
  - SQL Server (optionnel, entreprise)
  - Webhooks (token, URLs)
  - Breakout (DB type, cron, auto-seed)
  - Scheduler (enabled, interval, max jobs)
  - Logs & Monitoring (level, rotation, streaming)
  - API (rate limit, timeout, CORS)
  - Notifications (email, Slack, Teams)
  - Backup (enabled, cron, destination)
  - Cache, Session, Storage
  - Performance (PHP limits)
  - Sécurité (CSP, SSL/TLS)
  - Feature flags

**Documentation Existante (Assietou):**
- ✅ `DOC-20251121-WA0004.pdf` - Documentation technique breakout sélectif
  - Intégration PDO PostgreSQL
  - Mise à jour base de données
  - Transformation des scripts (cleanDictionarySchema, createDictionarySchema, generateDictionary, createDefaultTables)

##### Architecture Proposée

**Services Docker (7 containers):**
- CSWeb Core (Symfony 5 + PHP 8.0)
- Nginx (Reverse proxy + SSL)
- MySQL (Metadata CSWeb)
- PostgreSQL (Breakout analytics)
- Admin Panel (React 18 + TypeScript)
- Scheduler (Symfony Console + Supervisor)
- Prometheus + Grafana (Monitoring, optionnel)

**Stack Technique:**
- Backend : Symfony 5.4, PHP 8.0+, Doctrine DBAL, Monolog
- Frontend : React 18, TypeScript, Vite, Tailwind CSS
- DevOps : Docker, Docker Compose, Nginx, Supervisor
- Monitoring : Prometheus, Grafana
- Docs : Docusaurus, GitHub Pages

**Fonctionnalités Planifiées:**
- Breakout sélectif (✅ déjà implémenté)
- Multi-SGBD (PostgreSQL, MySQL, SQL Server)
- Scheduler Web UI (jobs configurables sans crontab)
- Monitoring temps réel (logs streaming SSE)
- Admin Panel moderne (React)
- API REST complète (CRUD dictionnaires, breakout, logs, schémas)

#### Modifié

- ✅ Projet CSWeb 8 PG original enrichi avec documentation complète
- ✅ Structure du projet réorganisée avec dossier `docs/`

#### Documentation

**Statistiques:**
- 11 documents Markdown
- ~7750 lignes de documentation
- ~255 pages (équivalent)
- 3 scripts PHP (webhooks)
- 1 fichier de configuration (.env.example)

**Temps de lecture estimé:**
- Démarrage (3 docs) : 30 min
- Planification (2 docs) : 50 min
- Migration (1 doc) : 40 min
- Webhooks/API (5 docs) : 1h 15min
- **Total : ~3h 15min** de lecture complète

**Réutilisation Kairos API:**
- Code backend : 90% réutilisable
- Webhooks PHP : 100% réutilisables
- Documentation : 87% réutilisable
- Tests : Patterns portables

**Gains Estimés:**
- Temps de développement : -63% (4.5 semaines vs 12)
- Qualité : Même niveau que Kairos (éprouvé en prod)
- Documentation : 87% de réutilisation (210 pages Kairos)

#### Contributeurs

- **Bouna DRAME** - Lead Developer, Documentation complète
- **Assietou Diagne (ANSD)** - Breakout sélectif, Support PostgreSQL

---

## [Unreleased] - Prochaines Versions

### v1.1.0 (À venir - Avril 2026)

#### Planifié

**Documentation:**
- [ ] `DEPLOYMENT-GUIDE.md` - Guide déploiement production
- [ ] `CONTRIBUTING.md` - Guide contribution
- [ ] `FAQ.md` - Questions fréquentes
- [ ] `CODE_OF_CONDUCT.md` - Code de conduite
- [ ] Tutoriels vidéo YouTube (12 vidéos FR+EN)

**Développement:**
- [ ] Docker Compose production-ready
- [ ] Admin Panel React (version alpha)
- [ ] API REST complète (endpoints CRUD)
- [ ] Scheduler service (background jobs)

---

### v1.5.0 (À venir - Septembre 2026)

#### Planifié

**Documentation:**
- [ ] `API-COMPLETE-REFERENCE.md` - API complète (OpenAPI/Swagger)
- [ ] `TESTING-GUIDE.md` - Tests unitaires/intégration
- [ ] `PERFORMANCE-GUIDE.md` - Optimisations
- [ ] `SECURITY-GUIDE.md` - Audit sécurité

**Fonctionnalités:**
- [ ] Support SQL Server (multi-SGBD complet)
- [ ] Dashboard Grafana intégré
- [ ] Notifications (Email, Slack, Teams)
- [ ] Backup/Restore automatique
- [ ] Multi-tenancy (plusieurs organisations)
- [ ] RBAC avancé (rôles custom)

---

### v2.5.0 (À venir - Décembre 2026)

#### Planifié

**Fonctionnalités:**
- [ ] High Availability (multi-servers)
- [ ] Load balancing automatique
- [ ] Réplication base de données
- [ ] Kubernetes support
- [ ] Plugins marketplace
- [ ] Templates dictionnaires

---

### v3.0.0 (À venir - Mars 2027)

#### Planifié

**Business:**
- [ ] Offre SaaS hébergée
- [ ] Plans (Free, Pro, Enterprise)
- [ ] Billing intégré (Stripe)
- [ ] White-label
- [ ] Support 24/7
- [ ] Formation certifiante
- [ ] Consulting services

---

## Types de Changements

- `Ajouté` - Nouvelles fonctionnalités
- `Modifié` - Changements dans les fonctionnalités existantes
- `Déprécié` - Fonctionnalités qui seront supprimées
- `Supprimé` - Fonctionnalités supprimées
- `Corrigé` - Corrections de bugs
- `Sécurité` - Correctifs de sécurité

---

## Liens

- [Documentation Complète](docs/README.md)
- [Guide Démarrage Rapide](GETTING-STARTED.md)
- [Index Navigation](DOCUMENTATION-INDEX.md)
- [Plan Stratégique](docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md)
- [Pont Kairos → CSWeb](docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md)

---

## Notes de Version

### v2.0.0 - Architecture Flexible

**Date:** 14 Mars 2026
**Statut:** Production Ready

Release majeure introduisant l'**architecture flexible** pour supporter les déploiements locaux ET distants avec 3 SGBD.

**Highlights:**
- ✅ Mode LOCAL + Mode REMOTE (2 serveurs séparés)
- ✅ Support 3 SGBD : PostgreSQL, MySQL, SQL Server
- ✅ Migration à chaud (changement mode/DB sans rebuild)
- ✅ Wizard installation interactif (3 étapes)
- ✅ RGPH5 Sénégal : Support natif (architecture 2 serveurs)
- ✅ Docker Compose avec profiles dynamiques
- ✅ Tous les drivers pré-installés (hot-swap)
- ✅ Documentation complète (600+ lignes ARCHITECTURE-FLEXIBLE.md)
- ✅ GitHub Pages avec Jekyll (Cayman theme)

**Cas d'usage:**
1. **Développeur local** : Tout en Docker (dev/test)
2. **RGPH5 Sénégal** : CSWeb (serveur 1) + SQL Server (serveur 2)
3. **Institut stats** : Breakout simultané de plusieurs enquêtes
4. **Migration progressive** : Tester PostgreSQL sur dictionnaire pilote

**Breaking changes:**
- Variables d'environnement : `BREAKOUT_MODE`, `BREAKOUT_DB_TYPE` obligatoires
- docker-compose : Lancement via `--profile` flag
- Migration v1.0→v2.0 : Backup/restore requis (voir CHANGELOG)

**Prochaines étapes:**
1. Beta testing RGPH5 (2 serveurs SQL Server)
2. Tutoriels vidéo (installation interactive)
3. Support communautaire (Discord)

---

### v1.0.0 - Breakout Sélectif + Documentation

**Date:** 14 Mars 2026
**Statut:** Stable

Première release stable avec breakout sélectif par dictionnaire et documentation complète.

**Highlights:**
- ✅ 11 documents Markdown (255 pages)
- ✅ Plan stratégique complet (roadmap jusqu'à 2027)
- ✅ Pont Kairos → CSWeb (guide migration complète)
- ✅ Documentation webhooks (4 docs, 90 pages)
- ✅ Variables d'environnement complètes
- ✅ 3 scripts PHP webhooks (prêts à déployer)
- ✅ Breakout sélectif (implémenté par Assietou Diagne, ANSD)
- ✅ Support PostgreSQL + MySQL pour breakout

**Réutilisation Kairos API:**
- Code backend : 90% réutilisable
- Webhooks PHP : 100% réutilisables
- Documentation : 87% réutilisable

---

**Mainteneur:** Bouna DRAME (bounafode@gmail.com)
**License:** MIT
**Projet:** https://github.com/BOUNADRAME/pg_csweb8_latest_2026
