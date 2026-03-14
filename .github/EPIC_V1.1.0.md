# [EPIC] Roadmap v1.1.0 - Docker Compose + Admin Panel

> **À créer comme Issue sur GitHub avec label `enhancement` et `epic`**

---

## 🎯 Objectif v1.1.0 (Avril 2026)

Rendre CSWeb Community Platform production-ready avec Docker Compose fonctionnel et une interface web moderne.

---

## 📦 Livrables

### 1. Docker Compose Production-Ready
- [ ] `docker-compose.yml` complet (7 services)
  - [ ] CSWeb Core (Symfony + PHP 8.0)
  - [ ] Nginx (reverse proxy + SSL)
  - [ ] MySQL (metadata)
  - [ ] PostgreSQL (analytics)
  - [ ] Admin Panel (React 18)
  - [ ] Scheduler (Supervisor)
  - [ ] Prometheus + Grafana (optionnel)

- [ ] Variables d'environnement finalisées
  - [ ] Validation `.env.example`
  - [ ] Documentation de chaque variable
  - [ ] Secrets sécurisés par défaut

- [ ] Scripts de démarrage
  - [ ] `scripts/start.sh` - Lancement complet
  - [ ] `scripts/stop.sh` - Arrêt propre
  - [ ] `scripts/backup.sh` - Backup databases
  - [ ] `scripts/restore.sh` - Restore databases

- [ ] Documentation Docker
  - [ ] Guide troubleshooting Docker
  - [ ] FAQ erreurs communes
  - [ ] Performance tuning

### 2. Admin Panel React (Version Alpha)

**Pages principales :**
- [ ] **Dashboard** - Vue d'ensemble (stats, jobs récents)
- [ ] **Dictionnaires** - Liste, upload, configuration
- [ ] **Breakout** - Déclenchement manuel, historique
- [ ] **Scheduler** - Gestion jobs (CRUD)
- [ ] **Logs** - Visualisation, filtres, streaming
- [ ] **Configuration** - Schémas DB, webhooks

**Features :**
- [ ] Authentification JWT
- [ ] UI responsive (Tailwind CSS)
- [ ] Temps réel (WebSocket ou SSE)
- [ ] Export CSV/JSON
- [ ] Dark mode (optionnel)

**Stack :**
- React 18 + TypeScript
- Vite (build tool)
- Tailwind CSS
- React Query (data fetching)
- Zustand (state management)

### 3. API REST Complète

**Endpoints à implémenter :**

**Dictionnaires**
- [ ] `GET /api/dictionaries` - Liste
- [ ] `POST /api/dictionaries` - Upload
- [ ] `GET /api/dictionaries/{id}` - Détail
- [ ] `DELETE /api/dictionaries/{id}` - Suppression

**Breakout**
- [ ] `POST /api/breakout/{dict}/trigger` - Déclenchement
- [ ] `GET /api/breakout/{dict}/status` - Statut
- [ ] `GET /api/breakout/{dict}/history` - Historique
- [ ] `GET /api/breakout/{dict}/logs` - Logs

**Scheduler**
- [ ] `GET /api/scheduler/jobs` - Liste jobs
- [ ] `POST /api/scheduler/jobs` - Créer job
- [ ] `PATCH /api/scheduler/jobs/{id}` - Modifier
- [ ] `DELETE /api/scheduler/jobs/{id}` - Supprimer
- [ ] `POST /api/scheduler/jobs/{id}/run` - Exécution manuelle

**Logs**
- [ ] `GET /api/logs` - Lecture (pagination, filtres)
- [ ] `GET /api/logs/stream` - Streaming SSE
- [ ] `GET /api/logs/export` - Export CSV

**Schémas**
- [ ] `GET /api/schemas` - Liste schémas
- [ ] `POST /api/schemas` - Créer schéma
- [ ] `PUT /api/schemas/{id}` - Modifier
- [ ] `DELETE /api/schemas/{id}` - Supprimer
- [ ] `POST /api/schemas/{id}/test` - Test connexion

**Documentation API**
- [ ] OpenAPI/Swagger spec
- [ ] Postman collection
- [ ] Exemples curl dans docs

### 4. Scheduler Service

**Features :**
- [ ] Background service (Supervisor)
- [ ] Exécution jobs selon cron
- [ ] Retry automatique (configurable)
- [ ] Notifications échec (email, Slack)
- [ ] Historique exécutions
- [ ] Logs détaillés

**Commandes Symfony :**
- [ ] `csweb:scheduler:run` - Lance le scheduler
- [ ] `csweb:scheduler:sync` - Sync jobs depuis DB
- [ ] `csweb:scheduler:list` - Liste jobs actifs

---

## 🧪 Tests

- [ ] Tests unitaires (PHPUnit)
  - [ ] Controllers
  - [ ] Services
  - [ ] Repositories
  - [ ] Coverage > 70%

- [ ] Tests d'intégration
  - [ ] API endpoints
  - [ ] Breakout workflow complet
  - [ ] Scheduler exécution

- [ ] Tests E2E (optionnel)
  - [ ] Admin Panel (Playwright)
  - [ ] Workflow complet user

---

## 📖 Documentation

- [ ] Mettre à jour GETTING-STARTED.md
- [ ] Créer DOCKER-GUIDE.md
- [ ] Créer API-REFERENCE.md (OpenAPI)
- [ ] Tutoriel vidéo YouTube (15 min)
- [ ] Traduire docs principales en anglais

---

## 🚀 Déploiement

- [ ] GitHub Actions workflow
  - [ ] Build Docker images
  - [ ] Push to Docker Hub
  - [ ] Tests automatiques

- [ ] Release v1.1.0
  - [ ] Tag Git
  - [ ] Release notes
  - [ ] Binary releases (optionnel)

---

## 📅 Timeline

| Phase | Durée | Deadline |
|-------|-------|----------|
| Phase 1: Docker Compose | 2 semaines | 15 Avril 2026 |
| Phase 2: API REST | 2 semaines | 30 Avril 2026 |
| Phase 3: Admin Panel Alpha | 3 semaines | 20 Mai 2026 |
| Phase 4: Scheduler | 1 semaine | 27 Mai 2026 |
| Phase 5: Tests + Docs | 1 semaine | 3 Juin 2026 |
| **Release v1.1.0** | - | **10 Juin 2026** |

---

## 👥 Contributeurs Recherchés

- **Backend Dev (Symfony/PHP)** - API REST, Scheduler
- **Frontend Dev (React/TS)** - Admin Panel
- **DevOps** - Docker, CI/CD
- **QA Tester** - Tests, feedback
- **Tech Writer** - Documentation, tutoriels

---

## 📊 Métriques de Succès

- ✅ Installation fonctionnelle en < 5 min
- ✅ Admin Panel utilisable (pas de bugs bloquants)
- ✅ API documentée (OpenAPI)
- ✅ Tests coverage > 70%
- ✅ 10+ beta testers satisfaits
- ✅ Tutoriel vidéo publié

---

## 🔗 Ressources

- [Plan stratégique](../blob/master/docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md)
- [Architecture Docker](../blob/master/docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md#architecture-docker)
- [Pont Kairos](../blob/master/docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md)

---

## 💬 Discussions

Pour toute question ou suggestion concernant cette roadmap, commentez ci-dessous ou ouvrez une discussion.

**Intéressé pour contribuer ?** Voir [CONTRIBUTING.md](../blob/master/CONTRIBUTING.md)

---

**Mainteneur :** @BOUNADRAME (Bouna DRAME)
**Label :** `enhancement`, `epic`, `v1.1.0`
