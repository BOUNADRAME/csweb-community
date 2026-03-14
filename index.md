---
layout: default
title: CSWeb Community Platform
---

# 🚀 CSWeb Community Platform

> **Transformez CSWeb 8 Vanilla en plateforme communautaire avec breakout sélectif par dictionnaire et support multi-base de données**

[![Documentation](https://img.shields.io/badge/docs-latest-blue.svg)](https://github.com/BOUNADRAME/pg_csweb8_latest_2026/tree/master/docs)
[![Docker](https://img.shields.io/badge/docker-ready-green.svg)](https://github.com/BOUNADRAME/pg_csweb8_latest_2026/blob/master/docker-compose.yml)
[![License](https://img.shields.io/badge/license-MIT-orange.svg)](LICENSE)

---

## ⚡ Installation Express (5 minutes)

```bash
git clone https://github.com/BOUNADRAME/pg_csweb8_latest_2026.git
cd pg_csweb8_latest_2026
chmod +x install.sh
./install.sh
```

Accédez à : **http://localhost:8080/setup/**

---

## ✨ Fonctionnalités

### 🎯 Breakout Sélectif par Dictionnaire

- ✅ **Isolation complète** : Chaque dictionnaire a ses propres tables
- ✅ **Multi-threading** : 3 threads par dictionnaire (500 cases chacun)
- ✅ **Label-based naming** : `{label}_cases`, `{label}_level_1`, etc.
- ✅ **Traçabilité** : Identification claire des données par dictionnaire

### 🗄️ Support Multi-Base de Données

- ✅ **PostgreSQL** (par défaut, recommandé)
- ✅ **MySQL** (compatible)
- ✅ **SQL Server** (supporté)

### 🐳 Docker Production-Ready

- ✅ **Stack complète** : MySQL + PostgreSQL + CSWeb + phpMyAdmin + pgAdmin
- ✅ **Installation automatique** : Script `install.sh` avec génération de mots de passe sécurisés
- ✅ **Health checks** : Surveillance automatique des services
- ✅ **Volumes persistants** : Données sauvegardées

---

## 📚 Documentation

### 🚀 Guides de Démarrage

| Guide | Description | Temps |
|-------|-------------|-------|
| [**Quick Start**](QUICK-START.html) | Installation en 5 minutes | ⏱️ 5 min |
| [**Package Complet**](PACKAGE-COMPLETE.html) | Vue d'ensemble du package | ⏱️ 10 min |
| [**Installation Vanilla**](docs/INSTALLATION-CSWEB-VANILLA.html) | CSWeb standard (setup.php) | ⏱️ 15 min |

### 🔧 Guides Techniques

| Guide | Description | Niveau |
|-------|-------------|--------|
| [**Migration Breakout Sélectif**](docs/MIGRATION-BREAKOUT-SELECTIF.html) | 21 transformations AVANT/APRÈS | 🔴 Avancé |
| [**Configuration Multi-DB**](docs/CONFIGURATION-MULTI-DATABASE.html) | PostgreSQL/MySQL/SQL Server | 🟡 Intermédiaire |
| [**Docker Deployment**](docs/DOCKER-DEPLOYMENT.html) | Production avec Docker | 🟡 Intermédiaire |

### 🔐 Guides d'Intégration

| Guide | Description | Stack |
|-------|-------------|-------|
| [**OAuth2 Authentication**](docs/CSWEB-OAUTH-AUTHENTICATION.html) | Token access/refresh | Spring Boot, Laravel, Express.js |
| [**Webhooks Integration**](docs/WEBHOOKS-INTEGRATION.html) | Événements temps réel | Spring Boot, Laravel, Express.js |
| [**Notes Configuration**](docs/NOTES-CONFIGURATION-CSWEB.html) | MySQL CSWeb vs Breakout | 🟢 Débutant |

---

## 🏗️ Architecture

### AVANT (CSWeb Vanilla)

```
Base de données unique
├── DICT_cases          ❌ Tous dictionnaires mélangés
├── DICT_level_1        ❌ Breakout global uniquement
└── DICT_record_001     ❌ Une seule DB à la fois
```

**Problème :** Impossible de faire du breakout simultané de plusieurs dictionnaires.

### APRÈS (Community Platform)

```
PostgreSQL (Breakout - par défaut)
├── survey_cases        ✅ Isolé par label
├── survey_level_1      ✅ Multi-threading
├── census_cases        ✅ Breakout simultané
├── census_level_1      ✅ Traçabilité claire
└── health_cases        ✅ Scalabilité

MySQL (Métadonnées CSWeb - fixe)
├── cspro_dictionaries
├── cspro_users
└── cspro_oauth_clients
```

**Avantage :** Breakout de plusieurs dictionnaires en parallèle avec isolation complète.

---

## 🎯 Cas d'Usage

### 1. Institut National de Statistique

**Scénario :** Gérer plusieurs enquêtes simultanément

```bash
# Breakout simultané de 3 enquêtes
docker-compose exec csweb php bin/console csweb:process-cases-by-dict \
  dictionnaires=RGPH_DICT,EDS_DICT,ENES_DICT
```

**Résultat :**
- `rgph_cases` (Recensement)
- `eds_cases` (Enquête Démographique)
- `enes_cases` (Enquête Emploi)

### 2. Projet de Recherche

**Scénario :** Base PostgreSQL pour analytics avancés

```bash
# .env
DEFAULT_BREAKOUT_DB_TYPE=postgresql
POSTGRES_DATABASE=research_analytics
```

**Avantages :**
- JSON natif
- Window functions
- Full-text search
- Meilleures performances

### 3. Migration Progressive

**Scénario :** Tester PostgreSQL sur un dictionnaire

```bash
# Dictionnaire PILOT → PostgreSQL
# Autres dictionnaires → MySQL (existant)
```

---

## 🚀 Quick Start Commands

### Installation

```bash
# Cloner
git clone https://github.com/BOUNADRAME/pg_csweb8_latest_2026.git
cd pg_csweb8_latest_2026

# Installer (auto)
chmod +x install.sh
./install.sh
```

### Vérification

```bash
# Vérifier drivers disponibles
docker-compose exec csweb php bin/console csweb:check-database-drivers

# Résultat attendu
# POSTGRESQL: ✅ Available
# MYSQL: ✅ Available
```

### Premier Breakout

```bash
# Breakout d'un dictionnaire
docker-compose exec csweb php bin/console csweb:process-cases-by-dict \
  dictionnaires=SURVEY_DICT

# Vérifier tables PostgreSQL
docker-compose exec postgres psql -U csweb_analytics -d csweb_analytics -c "\dt"
```

---

## 🛠️ Stack Technique

### Backend

- **PHP** : 8.1+
- **Symfony** : 5.4
- **Doctrine DBAL** : Abstraction multi-DB
- **CSWeb** : 8.0+ (vanilla compatible)

### Bases de Données

- **PostgreSQL** : 16 (analytics breakout)
- **MySQL** : 8.0 (métadonnées CSWeb)
- **SQL Server** : 2022 (optionnel)

### DevOps

- **Docker** : 20.10+
- **Docker Compose** : 2.0+
- **Apache** : 2.4
- **GitHub Actions** : CI/CD

---

## 📊 Statistiques

- **📄 Documentation** : 8000+ lignes (9 guides)
- **💻 Code PHP** : 1150+ lignes (3 services)
- **🐳 Docker** : Production-ready stack
- **🔧 Transformations** : 21 méthodes (AVANT/APRÈS)

---

## 🤝 Contribution

Ce projet capitalise sur les transformations réalisées par **Assietou Diagne** pour le projet **Kairos** (ANSD, Sénégal).

### Auteurs

- **Transformations originales** : Assietou Diagne (ANSD)
- **Documentation & Package** : Bouna DRAME
- **Integration Backend** : Kairos Project (Spring Boot)

### Contribuer

1. Fork le projet
2. Créer une branche (`git checkout -b feature/amazing`)
3. Commit (`git commit -m 'Add amazing feature'`)
4. Push (`git push origin feature/amazing`)
5. Ouvrir une Pull Request

---

## 📞 Support

- 📧 **Email** : bounafode@gmail.com
- 💬 **Discussions** : [GitHub Discussions](https://github.com/BOUNADRAME/pg_csweb8_latest_2026/discussions)
- 🐛 **Issues** : [GitHub Issues](https://github.com/BOUNADRAME/pg_csweb8_latest_2026/issues)
- 📖 **Documentation** : [docs/](https://github.com/BOUNADRAME/pg_csweb8_latest_2026/tree/master/docs)

---

## 📜 License

MIT License - voir [LICENSE](LICENSE) pour détails.

---

## 🌍 Démocratiser CSWeb pour l'Afrique

Ce projet vise à rendre CSWeb accessible à tous les instituts de statistiques, projets de recherche et organisations en Afrique.

**Objectifs :**
- ✅ Documentation française complète
- ✅ Installation simplifiée (5 minutes)
- ✅ Support multi-base de données
- ✅ Breakout sélectif et scalable
- ✅ Open source et communautaire

---

<div align="center">
  <p><strong>Made with ❤️ by Bouna DRAME</strong></p>
  <p>
    <a href="https://github.com/BOUNADRAME/pg_csweb8_latest_2026">⭐ Star on GitHub</a> •
    <a href="https://github.com/BOUNADRAME/pg_csweb8_latest_2026/fork">🍴 Fork</a> •
    <a href="https://github.com/BOUNADRAME/pg_csweb8_latest_2026/issues">🐛 Report Bug</a>
  </p>
</div>
