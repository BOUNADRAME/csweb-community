# CSWeb Community Platform

<div align="center">

![CSWeb Logo](https://img.shields.io/badge/CSWeb-Community-blue?style=for-the-badge)
![Version](https://img.shields.io/badge/version-8.0.1-green?style=for-the-badge)
[![License](https://img.shields.io/badge/License-Apache%202.0-green?style=for-the-badge)](LICENSE)
[![Documentation](https://img.shields.io/badge/docs-Nextra-brightgreen?style=for-the-badge)](https://bounadrame.github.io/csweb-community/)
[![GitHub Issues](https://img.shields.io/github/issues/BOUNADRAME/csweb-community?style=for-the-badge)](https://github.com/BOUNADRAME/csweb-community/issues)
[![GitHub Stars](https://img.shields.io/github/stars/BOUNADRAME/csweb-community?style=for-the-badge)](https://github.com/BOUNADRAME/csweb-community/stargazers)

**Democratiser CSWeb pour l'Afrique** - Setup en 5 minutes au lieu de 2-3 jours

[Documentation](https://bounadrame.github.io/csweb-community/) | [Issues](https://github.com/BOUNADRAME/csweb-community/issues)

</div>

---

## Vision

**CSWeb Community Platform** transforme CSWeb en une plateforme moderne, facile a deployer et accessible a tous les instituts statistiques.

> **Base officielle :** Ce projet est base sur **CSWeb 8** telecharge depuis le site officiel [csprousers.org/downloads](https://csprousers.org/downloads/). Toutes nos ameliorations sont construites sur cette base officielle et maintiennent une **compatibilite 100%** avec CSWeb vanilla.

| CSWeb Vanilla | CSWeb Community |
|---|---|
| Setup 2-3 jours | **Setup 5 minutes** (Docker) |
| MySQL uniquement | **Multi-DB** (PostgreSQL, MySQL, SQL Server) |
| Breakout global (lent) | **Breakout selectif** par dictionnaire |
| Pas de monitoring | **Logs streaming** temps reel |
| Documentation dispersee | **Documentation complete** (Nextra) |

---

## Quick Start

```bash
# 1. Cloner le projet
git clone https://github.com/BOUNADRAME/csweb-community.git
cd csweb-community

# 2. Configurer l'environnement
cp .env.example .env
# Editer .env : renseigner les mots de passe et choisir BREAKOUT_DB_TYPE

# 3. Lancer (voir section "Modes de deploiement" ci-dessous)
docker compose --profile local-postgres up -d
```

---

## Modes de deploiement

CSWeb supporte **6 configurations** selon le type de base breakout et le mode (local Docker ou serveur distant).

### Mode Local (Docker containers)

Les bases breakout tournent dans des containers Docker sur la meme machine.

#### Local + PostgreSQL (recommande)

```bash
# .env
BREAKOUT_MODE=local
BREAKOUT_DB_TYPE=postgresql

# Lancer
docker compose --profile local-postgres up -d
```

**Services :** CSWeb (:8080) + MySQL metadata (:3306) + PostgreSQL breakout (:5432)

#### Local + MySQL

```bash
# .env
BREAKOUT_MODE=local
BREAKOUT_DB_TYPE=mysql

# Lancer
docker compose --profile local-mysql up -d
```

**Services :** CSWeb (:8080) + MySQL metadata (:3306) + MySQL breakout (:3307)

#### Local + SQL Server

```bash
# .env
BREAKOUT_MODE=local
BREAKOUT_DB_TYPE=sqlserver

# Lancer
docker compose --profile local-sqlserver up -d
```

**Services :** CSWeb (:8080) + MySQL metadata (:3306) + SQL Server breakout (:1433)

### Mode Remote (serveur distant)

La base breakout est sur un serveur externe. Seuls CSWeb + MySQL metadata tournent en Docker.

#### Remote + PostgreSQL

```bash
# .env
BREAKOUT_MODE=remote
BREAKOUT_DB_TYPE=postgresql
POSTGRES_HOST=votre-serveur-pg.example.com
POSTGRES_PORT=5432
POSTGRES_DATABASE=csweb_analytics
POSTGRES_USER=csweb_analytics
POSTGRES_PASSWORD=votre_mot_de_passe

# Lancer (pas de profil - pas de container breakout)
docker compose up -d
```

**Services :** CSWeb (:8080) + MySQL metadata (:3306). PostgreSQL est sur le serveur distant.

#### Remote + MySQL

```bash
# .env
BREAKOUT_MODE=remote
BREAKOUT_DB_TYPE=mysql
MYSQL_BREAKOUT_HOST=votre-serveur-mysql.example.com
MYSQL_BREAKOUT_PORT=3306
MYSQL_BREAKOUT_DATABASE=csweb_breakout
MYSQL_BREAKOUT_USER=breakout_user
MYSQL_BREAKOUT_PASSWORD=votre_mot_de_passe

# Lancer
docker compose up -d
```

**Services :** CSWeb (:8080) + MySQL metadata (:3306). MySQL breakout est sur le serveur distant.

#### Remote + SQL Server

```bash
# .env
BREAKOUT_MODE=remote
BREAKOUT_DB_TYPE=sqlserver
SQLSERVER_HOST=votre-serveur-sql.example.com
SQLSERVER_PORT=1433
SQLSERVER_DATABASE=CSWeb_Analytics
SQLSERVER_USER=sa
SQLSERVER_PASSWORD=VotreMotDePasse!

# Lancer
docker compose up -d
```

**Services :** CSWeb (:8080) + MySQL metadata (:3306). SQL Server est sur le serveur distant.

### Outils de developpement (optionnel)

Ajouter `--profile dev` pour phpMyAdmin et pgAdmin :

```bash
# Exemple : Local PostgreSQL + outils dev
docker compose --profile local-postgres --profile dev up -d
```

**Services supplementaires :** phpMyAdmin (:8081) + pgAdmin (:8082)

### Resume des commandes

| Breakout | Local | Remote |
|----------|-------|--------|
| **PostgreSQL** | `docker compose --profile local-postgres up -d` | `docker compose up -d` |
| **MySQL** | `docker compose --profile local-mysql up -d` | `docker compose up -d` |
| **SQL Server** | `docker compose --profile local-sqlserver up -d` | `docker compose up -d` |

> En mode **remote**, configurer `BREAKOUT_MODE=remote` + les variables de connexion dans `.env`, puis `docker compose up -d` (sans profil).

---

## Premier Breakout

```bash
# Breakout selectif par dictionnaire
docker exec -it csweb-app php bin/console csweb:process-cases-by-dict VOTRE_DICTIONNAIRE
```

---

## Performance Tuning

Toutes les configurations PHP, MySQL, PostgreSQL et Apache sont pilotables depuis `.env`. Voir [.claude/PERFORMANCE-TUNING.md](.claude/PERFORMANCE-TUNING.md) pour le guide complet.

```bash
# Verifier la configuration
docker exec csweb-app php bin/console csweb:check-config

# Avec test des connexions
docker exec csweb-app php bin/console csweb:check-config --test-connections
```

---

## Stack Technique

- **Backend :** Symfony 5.4 LTS, PHP 8.1+
- **Databases :** MySQL 8.0 (metadata) + PostgreSQL 16 / MySQL 8.0 / SQL Server 2022 (breakout)
- **Frontend :** Twig, jQuery, Bootstrap 4, DataTables
- **DevOps :** Docker + Docker Compose
- **Documentation :** Nextra 2.13 (48 pages)

---

## Documentation

Documentation complete : **https://bounadrame.github.io/csweb-community/**

- [Installation](https://bounadrame.github.io/csweb-community/getting-started/installation/) - Setup en 5 minutes
- [Premier Breakout](https://bounadrame.github.io/csweb-community/getting-started/first-breakout/) - Tutorial complet
- [Architecture](https://bounadrame.github.io/csweb-community/guides/architecture/) - Multi-DB, Local/Remote
- [CLI Reference](https://bounadrame.github.io/csweb-community/reference/cli/commands/) - Commandes disponibles

---

## Contributeurs

**Bouna DRAME** - Lead Developer
- GitHub: [@BOUNADRAME](https://github.com/BOUNADRAME)
- [Portfolio](https://bounadrame.github.io/portfolio/)

**Assietou Diagne** - Developer, ANSD
- Breakout selectif par dictionnaire

---

## Production Validee

- **ANSD** (Senegal) - Recensement (RGPH5) et enquetes
- **Gambie** - Recensement
- **Guinee** - Recensement
- **Statinfo** - Enquetes

---

## License

**Apache License 2.0** - Voir [LICENSE](LICENSE)

---

## Support

- [Documentation](https://bounadrame.github.io/csweb-community/)
- [Signaler un bug](https://github.com/BOUNADRAME/csweb-community/issues/new)
- Email : bounafode@gmail.com | bdrame@statinfo.sn

---

<div align="center">

**Made with love for African Statistical Institutes**

</div>
