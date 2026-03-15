# Plan de Création des Pages Manquantes - Documentation Nextra

**Date :** 15 Mars 2026
**Status :** Analyse complète des liens brisés
**Objectif :** Créer toutes les pages référencées dans la navigation

---

## 📊 Résumé Exécutif

### Pages Existantes
✅ **8 pages complètes** créées :
1. `/` - Landing page
2. `/getting-started/` - Bienvenue
3. `/getting-started/installation` - Installation
4. `/getting-started/first-breakout` - Premier breakout
5. `/getting-started/verify` - Vérification
6. `/getting-started/next-steps` - Prochaines étapes
7. `/guides/architecture/csweb-postgresql-transformation` - Transformation PostgreSQL (Assietou)
8. `/guides/architecture/local-vs-remote` - Local vs Remote
9. `/reference/resources/contributors` - Contributeurs

### Pages Manquantes
❌ **35 pages** à créer (liens brisés détectés)

---

## 📂 Section 1: GUIDES - Breakout (3 pages CRITIQUES)

### 🔴 PRIORITÉ 1 (Essentiel)

#### 1. `/guides/breakout/selective.mdx`
**Importance :** 🔴 CRITIQUE
**Raison :** Lien principal depuis landing page (encadré vert Assietou)
**Source :** `docs/MIGRATION-BREAKOUT-SELECTIF.md` (1,390 lignes)
**Contenu :**
- Introduction au breakout sélectif
- Différence vs breakout global
- Commande CLI : `csweb:process-cases-by-dict`
- Exemples concrets (RGPH5)
- Configuration `.env`
- Troubleshooting

**Estimation :** 600-800 lignes

---

#### 2. `/guides/breakout/scheduled.mdx`
**Importance :** 🟡 Haute
**Source :** `docs/MIGRATION-BREAKOUT-SELECTIF.md` (section scheduler)
**Contenu :**
- Automatisation breakout avec scheduler
- Créer jobs cron via Admin Panel
- Patterns cron (quotidien, hebdomadaire)
- Monitoring jobs
- Notifications (email, Slack)

**Estimation :** 400-500 lignes

---

#### 3. `/guides/breakout/monitoring.mdx`
**Importance :** 🟡 Haute
**Contenu :**
- Logs streaming temps réel
- Métriques breakout (cases/s, errors, duration)
- Dashboard Grafana (optionnel)
- Alertes (jobs failed, timeout)
- Debugging breakout lent

**Estimation :** 350-450 lignes

---

## 📂 Section 2: GUIDES - Database (5 pages)

### 🔴 PRIORITÉ 1

#### 4. `/guides/database/multi-db.mdx`
**Importance :** 🔴 CRITIQUE
**Source :** `docs/CONFIGURATION-MULTI-DATABASE.md` (1,097 lignes)
**Contenu :**
- Comparaison PostgreSQL vs MySQL vs SQL Server
- Tableau comparatif (performances, features, coût)
- Quand choisir quel SGBD
- Migration entre SGBD (pg → mysql, mysql → sqlserver)
- Configuration `.env` pour chaque SGBD

**Estimation :** 700-900 lignes

---

#### 5. `/guides/database/postgresql.mdx`
**Importance :** 🟡 Haute
**Source :** `docs/CONFIGURATION-MULTI-DATABASE.md` (section PostgreSQL)
**Contenu :**
- Pourquoi PostgreSQL (analytics, JSON, window functions)
- Installation PostgreSQL (Docker local)
- Configuration remote PostgreSQL
- Optimisations (indexes, vacuum, analyze)
- Connexion Power BI/Tableau

**Estimation :** 500-600 lignes

---

#### 6. `/guides/database/mysql.mdx`
**Importance :** 🟡 Moyenne
**Source :** `docs/CONFIGURATION-MULTI-DATABASE.md` (section MySQL)
**Contenu :**
- MySQL pour breakout (compatible legacy)
- Configuration MySQL remote
- Différences avec metadata MySQL (ports 3306 vs 3307)
- Optimisations (my.cnf tuning)

**Estimation :** 400-500 lignes

---

#### 7. `/guides/database/sqlserver.mdx`
**Importance :** 🟡 Moyenne
**Source :** `docs/CONFIGURATION-MULTI-DATABASE.md` (section SQL Server)
**Contenu :**
- SQL Server pour RGPH5 (Microsoft ecosystem)
- Installation Linux (Docker)
- Configuration remote SQL Server
- Authentification Windows vs SQL
- Connexion via SSMS

**Estimation :** 450-550 lignes

---

## 📂 Section 3: GUIDES - Admin (4 pages)

#### 8. `/guides/admin/users.mdx`
**Importance :** 🟡 Moyenne
**Contenu :**
- Gestion utilisateurs CSWeb
- Créer comptes (Admin Panel + CLI)
- Rôles et permissions (admin, operator, viewer)
- OAuth externe (LDAP, Active Directory)
- Réinitialiser passwords

**Estimation :** 450-550 lignes

---

#### 9. `/guides/admin/scheduler.mdx`
**Importance :** 🟡 Haute
**Contenu :**
- Web Scheduler (alternative à crontab)
- Créer job breakout automatique
- Cron Builder visuel
- Patterns cron (*/5 * * * *, 0 2 * * *)
- Notifications (email success/failure)
- Monitoring jobs actifs

**Estimation :** 500-600 lignes

---

#### 10. `/guides/admin/monitoring.mdx`
**Importance :** 🟡 Haute
**Contenu :**
- Logs streaming temps réel (WebSocket)
- Métriques système (CPU, RAM, Disk)
- Métriques application (breakout/s, API calls)
- Alertes configurables
- Dashboard (Grafana optionnel)

**Estimation :** 400-500 lignes

---

#### 11. `/guides/admin/webhooks.mdx`
**Importance :** 🟡 Moyenne
**Source :** `docs/WEBHOOKS-INTEGRATION.md` (1,195 lignes - partie guide)
**Contenu :**
- Configuration webhooks CSWeb
- Événements (case_created, breakout_completed)
- Exemples intégrations (Slack, Discord, Email)
- Sécurité (HMAC signatures)
- Troubleshooting webhooks

**Estimation :** 550-650 lignes

---

## 📂 Section 4: GUIDES - Architecture (3 pages)

#### 12. `/guides/architecture/flexible.mdx`
**Importance :** 🔴 CRITIQUE
**Source :** `docs/ARCHITECTURE-FLEXIBLE.md` (730 lignes)
**Contenu :**
- Concepts architecture flexible
- 2 bases distinctes (metadata MySQL + breakout SGBD)
- Drivers installés (pdo_mysql, pdo_pgsql, sqlsrv)
- Variables .env essentielles
- Migration à chaud

**Estimation :** 600-700 lignes

---

#### 13. `/guides/architecture/security.mdx`
**Importance :** 🟡 Haute
**Contenu :**
- Best practices sécurité CSWeb
- HTTPS/TLS (Nginx reverse proxy + Let's Encrypt)
- Firewall rules (ufw, iptables)
- Sécuriser .env (chmod 600, encryption)
- OAuth2 authentication
- Rate limiting API

**Estimation :** 500-600 lignes

---

#### 14. `/guides/architecture/performance.mdx`
**Importance :** 🟡 Moyenne
**Contenu :**
- Optimisation PostgreSQL (shared_buffers, work_mem)
- Caching strategies (Redis, Memcached)
- Apache tuning (MaxRequestWorkers, KeepAlive)
- PHP-FPM tuning (pm.max_children)
- Scaling horizontal (load balancer + replicas)

**Estimation :** 450-550 lignes

---

## 📂 Section 5: GUIDES - Deployment (3 pages)

#### 15. `/guides/deployment/docker-prod.mdx`
**Importance :** 🟡 Haute
**Source :** `docs/DOCKER-DEPLOYMENT.md` (641 lignes - section prod)
**Contenu :**
- Docker production vs dev
- Environment variables production
- Volumes persistants (backup)
- Health checks
- Auto-restart policies
- Monitoring (cAdvisor, Prometheus)

**Estimation :** 500-600 lignes

---

#### 16. `/guides/deployment/remote.mdx`
**Importance :** 🟡 Haute
**Source :** `docs/ARCHITECTURE-FLEXIBLE.md` (section remote deployment)
**Contenu :**
- Déploiement remote (VPS, cloud)
- Configuration réseau (firewall, DNS)
- Nginx reverse proxy
- SSL certificates (Let's Encrypt)
- Backup automatique
- Monitoring (uptime, logs)

**Estimation :** 550-650 lignes

---

#### 17. `/guides/deployment/backup.mdx`
**Importance :** 🟡 Moyenne
**Contenu :**
- Stratégie backup (quotidien, hebdomadaire)
- Backup Docker volumes
- Backup PostgreSQL (pg_dump, pg_basebackup)
- Backup MySQL (mysqldump)
- Backup SQL Server (T-SQL backup)
- Restore procedures
- Test restore (validation)

**Estimation :** 450-550 lignes

---

## 📂 Section 6: REFERENCE - API (4 pages)

#### 18. `/reference/api/oauth.mdx`
**Importance :** 🔴 CRITIQUE
**Source :** `docs/CSWEB-OAUTH-AUTHENTICATION.md` (1,019 lignes)
**Contenu :**
- OAuth2 flow (grant_type=password)
- Obtenir token : POST `/api/token`
- Utiliser token : Header `Authorization: Bearer`
- Refresh token
- Scopes et permissions
- Exemples cURL, Postman, JavaScript

**Estimation :** 700-800 lignes

---

#### 19. `/reference/api/breakout.mdx`
**Importance :** 🟡 Haute
**Contenu :**
- API endpoints breakout
  - POST `/api/breakout/{dictionary}/trigger` - Lancer breakout
  - GET `/api/breakout/{dictionary}/status` - Statut
  - GET `/api/breakout/{dictionary}/logs` - Logs temps réel
- Request/Response examples
- Error codes
- Webhooks (événements breakout)

**Estimation :** 500-600 lignes

---

#### 20. `/reference/api/dictionaries.mdx`
**Importance :** 🟡 Moyenne
**Contenu :**
- API gestion dictionnaires
  - GET `/api/dictionaries` - Liste
  - POST `/api/dictionaries/upload` - Upload .dcf
  - GET `/api/dictionaries/{id}` - Détails
  - DELETE `/api/dictionaries/{id}` - Supprimer
- Metadata dictionnaire (version, levels, items)

**Estimation :** 450-550 lignes

---

#### 21. `/reference/api/webhooks.mdx`
**Importance :** 🟡 Moyenne
**Source :** `docs/WEBHOOKS-INTEGRATION.md` (1,195 lignes - partie API)
**Contenu :**
- API webhooks reference
  - POST `/api/webhooks` - Créer webhook
  - GET `/api/webhooks` - Liste
  - PUT `/api/webhooks/{id}` - Modifier
  - DELETE `/api/webhooks/{id}` - Supprimer
- Événements disponibles
- Payload structure
- Signature HMAC

**Estimation :** 500-600 lignes

---

## 📂 Section 7: REFERENCE - CLI (3 pages)

#### 22. `/reference/cli/overview.mdx`
**Importance :** 🟡 Moyenne
**Contenu :**
- Liste complète commandes CLI
- Syntaxe générale : `php bin/console csweb:command`
- Commandes essentielles (15+)
- Options globales (-h, --help, -v)
- Debugging (--verbose, --no-interaction)

**Estimation :** 400-500 lignes

---

#### 23. `/reference/cli/process-cases.mdx`
**Importance :** 🔴 CRITIQUE
**Source :** `docs/MIGRATION-BREAKOUT-SELECTIF.md` (section CLI)
**Contenu :**
- Commande `csweb:process-cases-by-dict`
- Arguments et options
  - `DICTIONARY_NAME` (requis)
  - `--dict-id=ID` (alternative)
  - `--limit=N` (limiter nombre cases)
  - `--verbose` (logs détaillés)
- Exemples concrets
- Output attendu
- Error handling

**Estimation :** 500-600 lignes

---

#### 24. `/reference/cli/check-drivers.mdx`
**Importance :** 🟡 Moyenne
**Contenu :**
- Commande `csweb:check-database-drivers`
- Vérifier drivers disponibles (pdo_pgsql, pdo_mysql, sqlsrv)
- Option `--test-connections` (tester connexions réelles)
- Troubleshooting drivers manquants
- Installation drivers (pdo_pgsql, sqlsrv)

**Estimation :** 350-450 lignes

---

## 📂 Section 8: REFERENCE - Config (3 pages)

#### 25. `/reference/config/environment.mdx`
**Importance :** 🔴 CRITIQUE
**Contenu :**
- Documentation complète `.env`
- **Toutes les variables** (80+) avec descriptions
- Sections :
  - Mode déploiement (BREAKOUT_MODE)
  - PostgreSQL (POSTGRES_*)
  - MySQL (MYSQL_BREAKOUT_*)
  - SQL Server (SQLSERVER_*)
  - CSWeb (CSWEB_*)
  - Sécurité (JWT_SECRET, OAUTH_*)
- Exemples configurations (dev, staging, prod)

**Estimation :** 700-900 lignes

---

#### 26. `/reference/config/docker-compose.mdx`
**Importance :** 🟡 Moyenne
**Contenu :**
- Structure `docker-compose.yml`
- Services disponibles (csweb, mysql, postgres, sqlserver, pgadmin)
- Profiles (local-postgres, local-mysql, local-sqlserver)
- Volumes (csweb_data, mysql_data, postgres_data)
- Networks (csweb_network)
- Commandes Docker Compose essentielles

**Estimation :** 500-600 lignes

---

#### 27. `/reference/config/drivers.mdx`
**Importance :** 🟡 Moyenne
**Source :** `docs/CONFIGURATION-MULTI-DATABASE.md` (section drivers)
**Contenu :**
- Drivers PHP installés (Dockerfile)
- pdo_mysql - Configuration + test
- pdo_pgsql - Configuration + test
- sqlsrv + pdo_sqlsrv - Configuration + test
- Vérifier disponibilité (`php -m | grep pdo`)
- Installer drivers manquants

**Estimation :** 400-500 lignes

---

## 📂 Section 9: REFERENCE - Troubleshooting (3 pages CRITIQUES)

### 🔴 PRIORITÉ 1 (Essentiel pour utilisateurs)

#### 28. `/reference/troubleshooting/common-issues.mdx`
**Importance :** 🔴 CRITIQUE
**Contenu :**
- **50+ problèmes courants** avec solutions
- Catégories :
  - Installation (Docker, .env)
  - Connexion DB (refused, timeout)
  - Breakout (echec, lent, erreurs SQL)
  - Performance (RAM, CPU)
  - Réseau (firewall, ports)
  - Drivers (pdo_pgsql not found)
  - Permissions (setup.php, volumes)
- Format : Symptôme → Cause → Solution

**Estimation :** 900-1,100 lignes

---

#### 29. `/reference/troubleshooting/faq.mdx`
**Importance :** 🔴 CRITIQUE
**Contenu :**
- **30+ questions fréquentes**
- Sections :
  - Général (CSWeb Community vs vanilla ?)
  - Installation (Quelle DB choisir ?)
  - Breakout (Sélectif vs global ?)
  - Production (Production-ready ?)
  - Migration (Local → Remote ?)
  - Sécurité (HTTPS obligatoire ?)
- Format Q&A concis

**Estimation :** 600-800 lignes

---

#### 30. `/reference/troubleshooting/errors.mdx`
**Importance :** 🟡 Haute
**Contenu :**
- **Référence codes d'erreur** CSWeb
- Erreurs PHP (SQLSTATE codes)
- Erreurs Docker (exit codes)
- Erreurs CSPro (breakout failures)
- Erreurs réseau (connection refused)
- Format : Code → Description → Solution

**Estimation :** 500-600 lignes

---

## 📂 Section 10: REFERENCE - Resources (3 pages)

#### 31. `/reference/resources/changelog.mdx`
**Importance :** 🟡 Moyenne
**Source :** `CHANGELOG.md` (si existe)
**Contenu :**
- Historique versions CSWeb Community
- v2.0.0 - Architecture flexible, PostgreSQL, Breakout sélectif
- v1.x - CSWeb vanilla baseline
- Format : Version → Date → Changes (Added, Changed, Fixed)

**Estimation :** 300-400 lignes

---

#### 32. `/reference/resources/roadmap.mdx`
**Importance :** 🟡 Moyenne
**Source :** `ROADMAP.md` (si existe)
**Contenu :**
- Roadmap CSWeb Community v2.x
- v2.1 (Q2 2026) - Admin Panel React, Real-time logs
- v2.2 (Q3 2026) - API GraphQL, Multi-tenancy
- v3.0 (Q4 2026) - Microservices architecture
- Contributeurs peuvent proposer features

**Estimation :** 350-450 lignes

---

#### 33. `/reference/resources/contributing.mdx`
**Importance :** 🟡 Moyenne
**Source :** `CONTRIBUTING.md` (si existe)
**Contenu :**
- Comment contribuer au projet
- Fork + Clone + Branch
- Code style (PSR-12 PHP, Prettier JS)
- Tests (PHPUnit, Jest)
- Pull Request process
- Code review
- Licence MIT

**Estimation :** 400-500 lignes

---

## 🎯 Plan d'Exécution Recommandé

### Phase 1 : Pages CRITIQUES (Semaine 1) - 7 pages

**Priorité MAXIMALE :**

1. ✅ `/guides/breakout/selective.mdx` (600-800 lignes)
2. ✅ `/guides/database/multi-db.mdx` (700-900 lignes)
3. ✅ `/guides/architecture/flexible.mdx` (600-700 lignes)
4. ✅ `/reference/api/oauth.mdx` (700-800 lignes)
5. ✅ `/reference/cli/process-cases.mdx` (500-600 lignes)
6. ✅ `/reference/config/environment.mdx` (700-900 lignes)
7. ✅ `/reference/troubleshooting/common-issues.mdx` (900-1,100 lignes)
8. ✅ `/reference/troubleshooting/faq.mdx` (600-800 lignes)

**Total Phase 1 :** ~5,400-6,700 lignes

---

### Phase 2 : Pages HAUTES (Semaine 2) - 10 pages

1. `/guides/breakout/scheduled.mdx`
2. `/guides/breakout/monitoring.mdx`
3. `/guides/database/postgresql.mdx`
4. `/guides/admin/scheduler.mdx`
5. `/guides/admin/monitoring.mdx`
6. `/guides/architecture/security.mdx`
7. `/guides/deployment/docker-prod.mdx`
8. `/guides/deployment/remote.mdx`
9. `/reference/api/breakout.mdx`
10. `/reference/troubleshooting/errors.mdx`

**Total Phase 2 :** ~5,000-6,000 lignes

---

### Phase 3 : Pages MOYENNES (Semaine 3) - 15 pages

Toutes les pages restantes (database, admin, deployment, API, CLI, config, resources)

**Total Phase 3 :** ~6,500-8,000 lignes

---

## 📊 Statistiques Finales

### Effort Total Estimé

| Phase | Pages | Lignes | Temps Estimé |
|-------|-------|--------|--------------|
| **Phase 1** | 8 | 5,400-6,700 | 16-20h |
| **Phase 2** | 10 | 5,000-6,000 | 12-16h |
| **Phase 3** | 15 | 6,500-8,000 | 16-20h |
| **TOTAL** | **33** | **~17,000-20,000** | **44-56h** |

### Pages Existantes vs Manquantes

```
Existantes:    8 pages  (25%)
Manquantes:   33 pages  (75%)
─────────────────────────────
TOTAL:        41 pages  (100%)
```

---

## 🔗 Fichiers Sources Disponibles

Les fichiers suivants dans `/docs/` peuvent être utilisés comme sources :

1. ✅ `ARCHITECTURE-FLEXIBLE.md` (730 lignes)
2. ✅ `CONFIGURATION-MULTI-DATABASE.md` (1,097 lignes)
3. ✅ `CSWEB-OAUTH-AUTHENTICATION.md` (1,019 lignes)
4. ✅ `DOCKER-DEPLOYMENT.md` (641 lignes)
5. ✅ `MIGRATION-BREAKOUT-SELECTIF.md` (1,390 lignes)
6. ✅ `WEBHOOKS-INTEGRATION.md` (1,195 lignes)
7. ✅ `INSTALLATION-CSWEB-VANILLA.md` (688 lignes)
8. ✅ `NOTES-CONFIGURATION-CSWEB.md` (403 lignes)

**Total sources disponibles :** ~7,200 lignes

---

## 🎯 Recommandation Finale

### Option 1 : Création Complète (Recommandé)

Créer **toutes les 33 pages manquantes** en 3 phases (3 semaines).

**Avantages :**
- ✅ Documentation 100% complète
- ✅ Aucun lien brisé
- ✅ Expérience utilisateur professionnelle
- ✅ Référence technique complète

**Inconvénients :**
- 44-56 heures de travail

---

### Option 2 : Pages CRITIQUES Seulement

Créer uniquement les **8 pages Phase 1** (semaine 1).

**Avantages :**
- ✅ 80% des besoins utilisateurs couverts
- ✅ 16-20h de travail
- ✅ Liens principaux fonctionnels

**Inconvénients :**
- ❌ 25 pages restent manquantes
- ❌ Liens brisés dans navigation

---

## 📝 Prochaine Action

**Choisissez une option :**

1. **Option A :** Créer Phase 1 (8 pages CRITIQUES) maintenant
2. **Option B :** Créer toutes les pages (33 pages) en 3 phases
3. **Option C :** Sélectionner manuellement les pages prioritaires

**Votre choix ?**

---

**Date :** 15 Mars 2026
**Analyse par :** Bouna DRAME (Expert Documentation)
**Basé sur :** Analyse complète des liens dans `/docs-nextra/pages/**/*.mdx`
