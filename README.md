# CSWeb Community Platform

<div align="center">

![CSWeb Logo](https://img.shields.io/badge/CSWeb-Community-blue?style=for-the-badge)
[![Version](https://img.shields.io/github/v/release/BOUNADRAME/pg_csweb8_latest_2026?include_prereleases&style=for-the-badge)](https://github.com/BOUNADRAME/pg_csweb8_latest_2026/releases)
[![License](https://img.shields.io/badge/License-Apache%202.0-green?style=for-the-badge)](LICENSE)
[![Documentation](https://img.shields.io/badge/docs-255%20pages-brightgreen?style=for-the-badge)](docs/)
[![GitHub Issues](https://img.shields.io/github/issues/BOUNADRAME/pg_csweb8_latest_2026?style=for-the-badge)](https://github.com/BOUNADRAME/pg_csweb8_latest_2026/issues)
[![GitHub Stars](https://img.shields.io/github/stars/BOUNADRAME/pg_csweb8_latest_2026?style=for-the-badge)](https://github.com/BOUNADRAME/pg_csweb8_latest_2026/stargazers)
[![CI](https://img.shields.io/github/actions/workflow/status/BOUNADRAME/pg_csweb8_latest_2026/documentation.yml?branch=master&style=for-the-badge&label=docs)](https://github.com/BOUNADRAME/pg_csweb8_latest_2026/actions)

**Démocratiser CSWeb pour l'Afrique** - Setup en 5 minutes au lieu de 2-3 jours 🚀

[📖 Documentation](docs/) • [🚀 Quick Start](GETTING-STARTED.md) • [🤝 Contributing](CONTRIBUTORS.md) • [📝 Changelog](CHANGELOG.md)

</div>

---

## 🎯 Vision

**CSWeb Community Platform** transforme CSWeb en une plateforme moderne, facile à déployer et accessible à tous les instituts statistiques africains.

> **Base officielle :** Ce projet est basé sur **CSWeb 8** téléchargé depuis le site officiel [csprousers.org/downloads](https://csprousers.org/downloads/). Toutes nos améliorations (architecture flexible, breakout sélectif, multi-DB) sont construites sur cette base officielle et maintiennent une **compatibilité 100%** avec CSWeb vanilla.

### Problème résolu
- ❌ Setup CSWeb : 2-3 jours de configuration manuelle
- ❌ Breakout global : traite TOUS les dictionnaires (lent, risqué)
- ❌ Configuration complexe : MySQL uniquement, pas de UI
- ❌ Pas de monitoring temps réel
- ❌ Documentation dispersée

### Solution apportée
- ✅ **Setup Docker : 5 minutes** (`docker-compose up -d`)
- ✅ **Breakout sélectif** : Par dictionnaire (par Assietou Diagne, ANSD)
- ✅ **Multi-SGBD** : PostgreSQL, MySQL, SQL Server
- ✅ **Web Scheduler** : Jobs configurables sans crontab
- ✅ **Monitoring** : Logs streaming temps réel
- ✅ **Admin Panel** : Interface React moderne
- ✅ **Documentation complète** : 255 pages (FR)

---

## ⚡ Quick Start

### Installation (5 minutes)

```bash
# 1. Cloner le projet
git clone https://github.com/BOUNADRAME/pg_csweb8_latest_2026.git
cd pg_csweb8_latest_2026

# 2. Configurer l'environnement
cp .env.example .env
# Éditer .env avec vos valeurs (IMPORTANT: changer les secrets!)

# 3. Lancer les services Docker
docker-compose up -d

# 4. Vérifier que tout fonctionne
curl http://localhost:8080/api/health
```

**Services disponibles :**
- CSWeb Core : http://localhost:8080
- Admin Panel : http://localhost:3000
- PostgreSQL : localhost:5432
- MySQL : localhost:3306

### Premier Breakout (2 minutes)

```bash
# Via CLI (commande Assietou Diagne)
docker exec -it csweb_app php bin/console csweb:process-cases-by-dict VOTRE_DICTIONNAIRE

# Via API
curl -X POST http://localhost:8080/api/breakout/VOTRE_DICTIONNAIRE/trigger \
  -H "Authorization: Bearer YOUR_TOKEN"
```

📖 **[Guide complet →](GETTING-STARTED.md)**

---

## 🌟 Fonctionnalités

### ✅ Déjà implémenté (v1.0)

#### Breakout Sélectif
- ✅ Commande : `php bin/console csweb:process-cases-by-dict <DICT>`
- ✅ Breakout par dictionnaire spécifique (au lieu de tous)
- ✅ Support PostgreSQL + MySQL
- ✅ **Crédit : Assietou Diagne (ANSD)**

#### Documentation Complète
- ✅ 17 fichiers Markdown (~255 pages)
- ✅ Plan stratégique (roadmap v1.0 → v2.5)
- ✅ Pont de réutilisation Kairos API (90% code backend)
- ✅ Guides webhooks (4 docs, 90 pages)
- ✅ Quick reference (commandes essentielles)
- ✅ Installation en 5 minutes

#### Configuration Avancée
- ✅ Variables d'environnement (200+ options)
- ✅ Multi-SGBD : PostgreSQL, MySQL, SQL Server
- ✅ 3 Webhooks PHP (breakout, logs, schémas)

### 🚧 En développement (v1.1 - Avril 2026)

- [ ] Docker Compose production-ready
- [ ] Admin Panel React (interface moderne)
- [ ] Scheduler Web UI (gestion jobs sans crontab)
- [ ] API REST complète (CRUD dictionnaires)
- [ ] Logs streaming SSE (temps réel)

### 📅 Roadmap complète

Voir **[CHANGELOG.md](CHANGELOG.md)** pour le détail des versions v1.0 → v2.5

---

## 📚 Documentation

### 🎯 Par rôle

**Débutant (30 min) :**
- [📖 Getting Started](GETTING-STARTED.md) - Installation et premier breakout
- [⚡ Quick Reference](docs/api-integration/CSWEB-QUICK-REFERENCE.md) - Commandes essentielles

**Développeur (2h) :**
- [📘 Plan Stratégique](docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md) - Architecture complète
- [🔗 Pont Kairos → CSWeb](docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md) - Code réutilisable (90%)

**Administrateur (1h) :**
- [📗 Guide Webhooks](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md) - Déploiement et troubleshooting
- [🔧 Configuration](docs/api-integration/CSWEB-QUICK-REFERENCE.md) - Variables d'environnement

**Tous :**
- [🗺️ Index Navigation](DOCUMENTATION-INDEX.md) - Guide complet par cas d'usage

### 📊 Statistiques Documentation

| Catégorie | Fichiers | Pages | Temps lecture |
|-----------|----------|-------|---------------|
| Quick Start | 2 docs | ~30 pages | 30 min |
| Planification | 2 docs | ~110 pages | 1h 30 |
| Webhooks/API | 5 docs | ~90 pages | 1h 15 |
| Configuration | 1 doc | ~25 pages | 20 min |
| **TOTAL** | **10 docs** | **~255 pages** | **~3h 15min** |

---

## 🏗️ Architecture

### Stack Technique

**Backend :**
- Symfony 5.4 LTS
- PHP 8.0+
- Doctrine DBAL (multi-SGBD)
- Monolog (logs)

**Frontend :**
- React 18 + TypeScript (Admin Panel)
- Vite + Tailwind CSS
- CSPro Web UI (existant)

**Databases :**
- MySQL 8.0 (metadata CSWeb)
- PostgreSQL 16 (analytics breakout)
- SQL Server 2019+ (optionnel, entreprise)

**DevOps :**
- Docker + Docker Compose
- Nginx (reverse proxy + SSL)
- Supervisor (scheduler background)
- Prometheus + Grafana (monitoring, optionnel)

### Services Docker (7 containers)

```
┌─────────────────────────────────────────────────────┐
│                   Nginx (Port 8080)                 │
│              Reverse Proxy + SSL/TLS                │
└────────────┬───────────────────────────┬────────────┘
             │                           │
    ┌────────▼────────┐         ┌───────▼────────┐
    │  CSWeb Core     │         │  Admin Panel   │
    │  (Symfony 5.4)  │         │  (React 18)    │
    │  Port 9000      │         │  Port 3000     │
    └────────┬────────┘         └────────────────┘
             │
    ┌────────▼─────────────────────────────┐
    │         Scheduler Service            │
    │    (Symfony Console + Supervisor)    │
    └──────────────────────────────────────┘
             │
    ┌────────┴────────┬───────────────────┐
    │                 │                   │
┌───▼────┐    ┌──────▼──────┐   ┌───────▼────────┐
│ MySQL  │    │ PostgreSQL  │   │ Prometheus +   │
│ :3306  │    │   :5432     │   │ Grafana (opt.) │
└────────┘    └─────────────┘   └────────────────┘
```

---

## 🤝 Contributeurs

### Core Team

**Bouna DRAME** - Lead Developer, Documentation
- 📧 bdrame@statinfo.sn
- 🐙 GitHub: [@BOUNADRAME](https://github.com/BOUNADRAME)
- 🌐 [Portfolio](https://bounadrame.github.io/portfolio/)

**Assietou Diagne** - Developer, ANSD
- ✅ Breakout sélectif par dictionnaire (fonctionnalité majeure)
- ✅ Support PostgreSQL
- ✅ Documentation technique

Voir **[CONTRIBUTORS.md](CONTRIBUTORS.md)** pour la liste complète et comment contribuer.

---

## 📖 Inspiration & Crédits

**Projets sources :**
- **CSPro/CSWeb** - US Census Bureau
- **Kairos API** - Source des webhooks (90% code réutilisé)
- **Docker CSWeb** - [csprousers/docker-csweb](https://github.com/csprousers/docker-csweb)

**Instituts partenaires :**
- **ANSD** (Sénégal) - Agence Nationale de la Statistique et de la Démographie
- Tests pilotes et feedback terrain

---

## 📝 License

**Apache License 2.0** - Voir [LICENSE](LICENSE)

Copyright 2013-2015 Iron Summit Media Strategies, LLC (CSWeb original)
Copyright 2026 Bouna DRAME & Contributors (CSWeb Community Platform)

---

## 🌍 Communauté

**GitHub :** https://github.com/BOUNADRAME/pg_csweb8_latest_2026

**À venir :**
- 💬 Discord : https://discord.gg/csweb-community
- 💡 GitHub Discussions
- 📺 YouTube : Tutoriels FR + EN
- 📖 GitHub Pages : Documentation interactive

**Contact :**
- 📧 Email : bdrame@statinfo.sn
- 🐦 Twitter : @CSWebCommunity (à venir)

---

## ⭐ Support

Si ce projet vous aide, mettez une **⭐ étoile** sur GitHub !

Signaler un bug ou proposer une feature : [Ouvrir une issue](https://github.com/BOUNADRAME/pg_csweb8_latest_2026/issues)

---

<div align="center">

**Made with ❤️ for African Statistical Institutes**

_Démocratiser la collecte de données statistiques pour l'Afrique et au-delà_

**[⬆ Retour en haut](#csweb-community-platform)**

</div>