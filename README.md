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

# 3. Lancer (PostgreSQL par defaut)
docker compose --profile local-postgres up -d
```

**Services disponibles :**
- CSWeb : http://localhost:8080
- PostgreSQL : localhost:5432
- MySQL (metadata) : localhost:3306

### Premier Breakout

```bash
# Breakout selectif par dictionnaire
docker exec -it csweb_app php bin/console csweb:process-cases-by-dict VOTRE_DICTIONNAIRE
```

---

## Stack Technique

- **Backend :** Symfony 5.4 LTS, PHP 8.1+
- **Databases :** MySQL 8.0 (metadata) + PostgreSQL 16 (analytics breakout)
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
