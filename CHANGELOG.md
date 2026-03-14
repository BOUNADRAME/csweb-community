# Changelog - CSWeb Community Platform

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

---

## [1.0.0-beta] - 2026-03-14

### 🎉 Première Release - Documentation Complète

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

### v2.0.0 (À venir - Décembre 2026)

#### Planifié

**Fonctionnalités:**
- [ ] High Availability (multi-servers)
- [ ] Load balancing automatique
- [ ] Réplication base de données
- [ ] Kubernetes support
- [ ] Plugins marketplace
- [ ] Templates dictionnaires

---

### v2.5.0 (À venir - Mars 2027)

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

### v1.0.0-beta - Documentation Complète

**Date:** 14 Mars 2026
**Statut:** Beta (Documentation uniquement)

Cette première release se concentre sur la **documentation exhaustive** du projet CSWeb Community Platform. Le code de production sera développé dans les versions suivantes (v1.1+).

**Highlights:**
- ✅ 11 documents Markdown (255 pages)
- ✅ Plan stratégique complet (roadmap jusqu'à 2027)
- ✅ Pont Kairos → CSWeb (guide migration complète)
- ✅ Documentation webhooks (4 docs, 90 pages)
- ✅ Variables d'environnement complètes
- ✅ 3 scripts PHP webhooks (prêts à déployer)
- ✅ Breakout sélectif (déjà implémenté par Assietou)

**Prochaines étapes:**
1. Setup repo GitHub public
2. Créer Discord communauté
3. Premiers tutoriels vidéo
4. Recherche beta testers (ANSD + 2-3 instituts)

---

**Mainteneur:** Bouna DRAME (bdrame@statinfo.sn)
**License:** MIT
**Projet:** https://github.com/bounadrame/csweb-community (à venir)
