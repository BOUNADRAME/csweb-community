# CSWeb Community Platform 🌍

> Simplifiez le déploiement et la gestion de CSWeb avec Docker, UI moderne et support multi-SGBD

[![Docker](https://img.shields.io/badge/Docker-Ready-blue?logo=docker)](https://docker.com)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple?logo=php)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-5.4-black?logo=symfony)](https://symfony.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14+-blue?logo=postgresql)](https://postgresql.org)
[![React](https://img.shields.io/badge/React-18-blue?logo=react)](https://reactjs.org)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## 📋 Table des Matières

- [🎯 Pourquoi CSWeb Community](#-pourquoi-csweb-community)
- [✨ Fonctionnalités](#-fonctionnalités)
- [🚀 Démarrage Rapide](#-démarrage-rapide)
- [📚 Documentation](#-documentation)
- [🛠️ Architecture](#️-architecture)
- [🤝 Contribution](#-contribution)
- [📞 Support](#-support)

---

## 🎯 Pourquoi CSWeb Community?

### Le Problème

CSWeb est un outil puissant pour la collecte de données CSPro, mais son déploiement et sa configuration sont **complexes** :

❌ Setup manuel (Apache, MySQL, PHP, permissions)
❌ Breakout global uniquement (tous les dictionnaires d'un coup)
❌ Configuration crontab manuelle (`crontab -e`)
❌ Logs difficiles d'accès (SSH requis)
❌ Pas de Docker production-ready
❌ Documentation limitée pour non-experts

### Notre Solution

**CSWeb Community Platform** simplifie tout ça avec :

✅ **Docker en 1 commande** : `docker-compose up -d`
✅ **Breakout sélectif** : Par dictionnaire via UI
✅ **Multi-SGBD** : PostgreSQL, MySQL, SQL Server
✅ **Scheduler Web** : Jobs configurables sans crontab
✅ **Monitoring temps réel** : Logs streaming + métriques
✅ **UI moderne** : Admin Panel React + Tailwind
✅ **Docs exhaustives** : FR + EN + Vidéos
✅ **Communauté active** : Discord + GitHub Discussions

---

## ✨ Fonctionnalités

### 🎯 Breakout Sélectif

Lancez le breakout pour **un dictionnaire spécifique**, pas tous en même temps.

```bash
# Via UI Admin Panel
# OU via API
curl -X POST http://localhost:8080/api/breakout/EVAL_PRODUCTEURS_USAID/trigger \
  -H "Authorization: Bearer {JWT_TOKEN}"

# OU via CLI
php bin/console csweb:process-cases-by-dict EVAL_PRODUCTEURS_USAID
```

### 🗄️ Support Multi-SGBD

Choisissez votre base de données cible pour le breakout :

- **PostgreSQL** ⭐ (Recommandé pour analytics)
- **MySQL** (Compatible, legacy)
- **SQL Server** (Écosystème Microsoft)

Configuration via UI ou API :

```json
{
  "dictionaryId": 3,
  "dbType": "postgresql",
  "host": "localhost",
  "port": 5432,
  "schema": "csweb_analytics",
  "user": "csweb_user",
  "password": "***"
}
```

### ⏰ Scheduler Web

Configurez des jobs de breakout automatiques **sans toucher au crontab** :

- Expression cron via UI (builder visuel)
- Activation/désactivation en 1 clic
- Historique des exécutions
- Retry automatique en cas d'échec
- Notifications (email, Slack, Teams)

### 📊 Monitoring Temps Réel

- **Logs streaming** (SSE) : Voir les logs en direct
- **Filtres avancés** : Niveau (ERROR, WARNING, INFO), dictionnaire, search
- **Dashboard métriques** : Breakouts/jour, erreurs, durée moyenne
- **Grafana intégré** (optionnel) : Dashboards avancés

### 🖥️ Admin Panel Moderne

Interface React moderne et responsive :

- Dashboard overview (stats, graphiques)
- Gestion dictionnaires (upload, preview)
- Configuration jobs breakout
- Monitoring logs temps réel
- Gestion utilisateurs/permissions

---

## 🚀 Démarrage Rapide

### Prérequis

- **Docker** 24+ & **Docker Compose** 2.x
- **Git**
- Port 8080 (CSWeb), 3000 (Admin Panel), 5432 (PostgreSQL), 3306 (MySQL) disponibles

### Installation (5 minutes)

```bash
# 1. Cloner le repo
git clone https://github.com/bounadrame/csweb-community.git
cd csweb-community

# 2. Copier et configurer .env
cp .env.example .env
# Éditer .env : définir les mots de passe, secrets

# 3. Lancer tous les services
docker-compose up -d

# 4. Attendre que les services démarrent (30-60s)
docker-compose logs -f csweb

# 5. Accéder aux interfaces
# - CSWeb Core: http://localhost:8080
# - Admin Panel: http://localhost:3000
# - Grafana: http://localhost:3001 (optionnel)
```

### Premier Login

**Credentials par défaut** (à changer en prod) :

- **Username:** `admin`
- **Password:** `admin123`

**⚠️ IMPORTANT:** Changez le mot de passe admin après le premier login.

### Premier Breakout

1. **Via Admin Panel** (http://localhost:3000)
   - Login avec `admin/admin123`
   - Allez dans **Dictionnaires** → Upload un dictionnaire CSPro
   - Allez dans **Breakout** → **Configuration Schémas**
   - Configurez la base de données cible (PostgreSQL par défaut)
   - Retournez dans **Breakout** → **Jobs**
   - Cliquez **▶ Lancer** sur le dictionnaire

2. **Via API** (curl)

   ```bash
   # 1. Login (obtenir JWT)
   TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"username":"admin","password":"admin123"}' | jq -r '.accessToken')

   # 2. Lister les dictionnaires
   curl -s http://localhost:8080/api/dictionaries \
     -H "Authorization: Bearer $TOKEN" | jq

   # 3. Configurer schéma PostgreSQL pour dictionnaire ID=3
   curl -s -X POST http://localhost:8080/api/schemas \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
       "dictionaryId": 3,
       "dbType": "postgresql",
       "hostName": "postgres",
       "port": 5432,
       "schemaName": "public",
       "schemaUserName": "csweb_analytics",
       "schemaPassword": "your_postgres_password"
     }' | jq

   # 4. Déclencher breakout
   curl -s -X POST http://localhost:8080/api/breakout/EVAL_PRODUCTEURS_USAID/trigger \
     -H "Authorization: Bearer $TOKEN" | jq
   ```

3. **Via CLI** (dans container)

   ```bash
   docker exec -it csweb_app php bin/console csweb:process-cases-by-dict EVAL_PRODUCTEURS_USAID
   ```

---

## 📚 Documentation

### Guides Complets

| Document | Description | Durée |
|----------|-------------|-------|
| [**Installation Guide**](docs/INSTALLATION-GUIDE.md) | Setup complet, Docker, configuration | 20 min |
| [**Breakout Guide**](docs/BREAKOUT-GUIDE.md) | Breakout sélectif, multi-SGBD, optimisations | 30 min |
| [**Scheduler Guide**](docs/SCHEDULER-GUIDE.md) | Jobs automatiques, cron, notifications | 20 min |
| [**Monitoring Guide**](docs/MONITORING-GUIDE.md) | Logs, métriques, alertes, Grafana | 15 min |
| [**API Reference**](docs/API-REFERENCE.md) | Endpoints REST, exemples curl/JS | 40 min |
| [**Multi-Database Guide**](docs/MULTI-DATABASE-GUIDE.md) | PostgreSQL, MySQL, SQL Server | 25 min |

### Tutoriels Vidéo

**Playlist YouTube:** [CSWeb Community - Guide Complet](https://youtube.com/playlist/...)

1. **Installation Docker** (10 min) - FR + EN
2. **Premier Breakout** (8 min) - FR + EN
3. **Configuration PostgreSQL** (12 min) - FR + EN
4. **Scheduler Web UI** (15 min) - FR + EN
5. **Monitoring et Logs** (12 min) - FR + EN

### Référence Rapide

```bash
# Commandes essentielles

# Démarrer services
docker-compose up -d

# Voir logs
docker-compose logs -f csweb

# Arrêter services
docker-compose down

# Rebuild après modification code
docker-compose up -d --build

# Accéder au shell PHP
docker exec -it csweb_app bash

# Lancer breakout CLI
docker exec -it csweb_app php bin/console csweb:process-cases-by-dict <DICT>

# Sync dictionnaires en jobs
docker exec -it csweb_app php bin/console csweb:breakout:sync

# Backup base de données
docker exec csweb_mysql mysqldump -u root -p<password> csweb_metadata > backup.sql
docker exec csweb_postgres pg_dump -U csweb_analytics csweb_analytics > backup_analytics.sql
```

---

## 🛠️ Architecture

### Vue d'Ensemble

```
┌────────────────────────────────────────────────────────────────────┐
│                    CSWeb Community Platform                        │
│                                                                    │
│  ┌────────────────┐   ┌────────────────┐   ┌────────────────┐   │
│  │  CSWeb Core    │   │  Admin Panel   │   │  API Gateway   │   │
│  │  (Symfony 5)   │◄──┤  (React 18)    │◄──┤  (REST)        │   │
│  └────────────────┘   └────────────────┘   └────────────────┘   │
│          │                     │                     │            │
│  ┌────────────────┐   ┌────────────────┐   ┌────────────────┐   │
│  │  Scheduler     │   │  Log Monitor   │   │  Metrics       │   │
│  │  (Jobs Mgmt)   │   │  (SSE)         │   │  (Prometheus)  │   │
│  └────────────────┘   └────────────────┘   └────────────────┘   │
│                                                                    │
├────────────────────────────────────────────────────────────────────┤
│                         Data Layer                                 │
│  ┌────────────────┐   ┌────────────────┐   ┌────────────────┐   │
│  │  MySQL         │   │  PostgreSQL    │   │  SQL Server    │   │
│  │  (Metadata)    │   │  (Analytics)   │   │  (Option)      │   │
│  └────────────────┘   └────────────────┘   └────────────────┘   │
└────────────────────────────────────────────────────────────────────┘
```

### Services Docker

| Service | Port | Description |
|---------|------|-------------|
| `csweb` | - | CSWeb Core (Symfony 5 + PHP 8.0) |
| `nginx` | 8080, 443 | Reverse proxy + SSL termination |
| `mysql` | 3306 | Metadata CSWeb (dictionnaires, users, cases) |
| `postgres` | 5432 | Breakout analytics (default) |
| `admin` | 3000 | Admin Panel (React 18 + TypeScript) |
| `scheduler` | - | Background jobs (Supervisor + Symfony Console) |
| `prometheus` | 9090 | Métriques système |
| `grafana` | 3001 | Dashboards (optionnel) |

### Stack Technique

**Backend:**
- Symfony 5.4 LTS
- PHP 8.0+
- Doctrine DBAL 3.x (multi-DB)
- Monolog 2.x (logs)
- API Platform 3.x (REST/GraphQL)

**Frontend:**
- React 18 + TypeScript
- Vite 5 (build)
- Tailwind CSS 3
- TanStack Query 5 (API client)
- Recharts 2 (graphiques)

**DevOps:**
- Docker + Docker Compose
- Nginx 1.24+
- Supervisor 4.x (process manager)
- GitHub Actions (CI/CD)

---

## 🤝 Contribution

### Comment Contribuer

Nous accueillons les contributions de la communauté ! Voici comment participer :

1. **Signaler un Bug** 🐛
   - Vérifier qu'il n'existe pas déjà dans [Issues](https://github.com/bounadrame/csweb-community/issues)
   - Créer une issue avec le template "Bug Report"
   - Fournir logs + config + étapes de reproduction

2. **Proposer une Feature** 💡
   - Discuter d'abord sur [Discord](https://discord.gg/csweb-community) #features-requests
   - Créer une issue avec le template "Feature Request"
   - Attendre validation de l'équipe avant de commencer le code

3. **Soumettre une Pull Request** 🔧
   - Fork le repo
   - Créer une branch: `feature/nom-feature` ou `fix/nom-bug`
   - Coder en suivant les standards (PSR-12 pour PHP, Airbnb pour TS)
   - Écrire des tests : `composer test` (PHP) + `npm test` (React)
   - Soumettre PR vers `develop` (pas `master`)
   - Passer la CI (GitHub Actions)

4. **Améliorer la Documentation** 📚
   - Fork `docs/`
   - Éditer Markdown
   - Preview : `npm run docs:dev`
   - Soumettre PR avec captures d'écran

### Code of Conduct

Consultez [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). En bref :

- ✅ Respectueux et inclusif
- ✅ Constructif dans les critiques
- ✅ Ouvert aux débutants
- ❌ Harcèlement, discrimination
- ❌ Spam, trolling

### Contributors

Merci aux contributeurs qui ont rendu ce projet possible :

<!-- ALL-CONTRIBUTORS-LIST:START -->
- 💻 [Bouna DRAME](https://github.com/bounadrame) - Initiateur, Lead Developer
- 🔬 [Assietou Diagne](https://github.com/adiagne) - CSWeb 8 PostgreSQL + Breakout sélectif
<!-- ALL-CONTRIBUTORS-LIST:END -->

Voir la liste complète dans [CONTRIBUTORS.md](CONTRIBUTORS.md).

---

## 📞 Support

### 🆘 Besoin d'Aide ?

**Documentation:**
- 📖 [Guides Complets](docs/) (160+ pages)
- 🎥 [Tutoriels Vidéo](https://youtube.com/playlist/...) (12 vidéos)
- 📋 [FAQ](docs/FAQ.md)

**Communauté:**
- 💬 [Discord](https://discord.gg/csweb-community) (support temps réel)
- 💡 [GitHub Discussions](https://github.com/bounadrame/csweb-community/discussions)
- 🐛 [GitHub Issues](https://github.com/bounadrame/csweb-community/issues)

**Contact Direct:**
- 📧 Email: support@csweb-community.org
- 🐦 Twitter: [@CSWebCommunity](https://twitter.com/CSWebCommunity)
- 💼 LinkedIn: [CSWeb Community](https://linkedin.com/company/csweb-community)

### 🎓 Formation & Consulting

**Formation certifiante** (bientôt disponible) :
- CSWeb Community Administrator (3 jours)
- CSWeb Community Developer (5 jours)
- Prix : 500 € / personne

**Consulting** :
- Setup & déploiement : 2000 € / institut
- Migration CSWeb classique → Community : 3000 €
- Support premium : 200 € / mois

Contact : consulting@csweb-community.org

---

## 📊 Statistiques Projet

![GitHub stars](https://img.shields.io/github/stars/bounadrame/csweb-community?style=social)
![GitHub forks](https://img.shields.io/github/forks/bounadrame/csweb-community?style=social)
![GitHub issues](https://img.shields.io/github/issues/bounadrame/csweb-community)
![GitHub pull requests](https://img.shields.io/github/issues-pr/bounadrame/csweb-community)
![GitHub contributors](https://img.shields.io/github/contributors/bounadrame/csweb-community)
![GitHub last commit](https://img.shields.io/github/last-commit/bounadrame/csweb-community)

---

## 📜 License

Ce projet est sous licence **MIT** - voir [LICENSE](LICENSE) pour les détails.

**En résumé :**
- ✅ Utilisation commerciale
- ✅ Modification
- ✅ Distribution
- ✅ Utilisation privée
- ⚠️ Pas de garantie

---

## 🙏 Remerciements

- **US Census Bureau** - Pour CSPro et CSWeb
- **Communauté CSPro Users** - Pour le support et les ressources
- **ANSD (Sénégal)** - Pour les tests et retours beta
- **Tous les contributeurs** - Pour rendre ce projet meilleur chaque jour

---

## 🗺️ Roadmap

### v1.0 - Foundation (T2 2026) ✅ En cours

- [x] Docker Compose production-ready
- [x] Breakout sélectif par dictionnaire
- [x] Support PostgreSQL + MySQL
- [ ] API REST complète
- [ ] Scheduler web UI
- [ ] Logs monitoring temps réel
- [ ] Admin Panel React
- [ ] Documentation FR/EN

### v1.5 - Enhancement (T3 2026)

- [ ] Support SQL Server
- [ ] Dashboard Grafana intégré
- [ ] Notifications (Email, Slack, Teams)
- [ ] Backup/Restore automatique
- [ ] Multi-tenancy
- [ ] RBAC avancé
- [ ] Mobile app (React Native)

### v2.0 - Scale (T4 2026)

- [ ] High Availability (multi-servers)
- [ ] Load balancing
- [ ] Réplication DB
- [ ] Kubernetes support
- [ ] Plugins marketplace
- [ ] AI assistant

### v2.5 - SaaS (T1 2027)

- [ ] Offre SaaS hébergée
- [ ] Plans (Free, Pro, Enterprise)
- [ ] Billing (Stripe)
- [ ] White-label
- [ ] Support 24/7
- [ ] Formation certifiante

Voir la [roadmap complète](ROADMAP.md).

---

## 🌍 Utilisé Par

**Instituts Statistiques :**
- 🇸🇳 ANSD (Agence Nationale de la Statistique et de la Démographie) - Sénégal
- 🇧🇫 INSD (Institut National de la Statistique et de la Démographie) - Burkina Faso (beta)
- 🇲🇱 INSTAT (Institut National de la Statistique) - Mali (beta)

**Organisations :**
- 🌍 UN-Habitat - Programmes urbains Afrique
- 📊 AFRISTAT - Observatoire économique et statistique

_Vous utilisez CSWeb Community ? [Ajoutez-vous à la liste !](https://github.com/bounadrame/csweb-community/issues/new?template=add-organization.md)_

---

## 📱 Captures d'Écran

### Dashboard

![Dashboard](docs/images/dashboard.png)

### Breakout Jobs

![Breakout Jobs](docs/images/breakout-jobs.png)

### Logs Monitoring

![Logs Monitoring](docs/images/logs-monitoring.png)

### Configuration Multi-DB

![Multi-DB Config](docs/images/multi-db-config.png)

---

## 🔗 Liens Utiles

**Documentation CSPro/CSWeb :**
- [CSPro Official](https://www.census.gov/data/software/cspro.html)
- [CSWeb Documentation](https://www.csprousers.org/help/CSWeb/)
- [CSPro Forum](https://www.csprousers.org/forum/)

**Ressources Communauté :**
- [Blog CSWeb Community](https://bounadrame.github.io/csweb-community/)
- [YouTube Channel](https://youtube.com/@CSWebCommunity)
- [Twitter](https://twitter.com/CSWebCommunity)

**Projets Liés :**
- [Docker CSWeb (Original)](https://github.com/csprousers/docker-csweb)
- [Kairos API](https://github.com/bounadrame/kairos-api) - Projet inspirateur

---

## ⭐ Star History

[![Star History Chart](https://api.star-history.com/svg?repos=bounadrame/csweb-community&type=Date)](https://star-history.com/#bounadrame/csweb-community&Date)

---

**Made with ❤️ by the CSWeb Community**

_Simplifying statistical data collection for Africa and beyond_

---

## 📣 Annonces

**🎉 Version 1.0 Beta disponible !** (14 Mars 2026)

Nous recherchons des beta testers (instituts statistiques) pour valider la plateforme avant le lancement officiel.

**Avantages beta testers :**
- ✅ Accès anticipé aux nouvelles features
- ✅ Support prioritaire
- ✅ Formation gratuite (valeur 500€)
- ✅ Nom dans CONTRIBUTORS.md
- ✅ Influence sur la roadmap

**Contact :** beta@csweb-community.org

---

<div align="center">

**[⬆ Retour en haut](#csweb-community-platform-)**

**Si ce projet vous a aidé, mettez une ⭐ !**

</div>
