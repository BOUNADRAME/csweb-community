# Documentation CSWeb Community Platform

> Documentation complète pour le projet CSWeb Community - Démocratiser CSWeb pour l'Afrique

**Date de création:** Mars 2026
**Version:** 1.0
**Auteurs:** Bouna DRAME, Assietou Diagne (ANSD)

---

## 📋 Table des Matières

1. [Vue d'Ensemble](#vue-densemble)
2. [Structure de la Documentation](#structure-de-la-documentation)
3. [Documentation Technique](#documentation-technique)
4. [Guides de Démarrage](#guides-de-démarrage)
5. [Contribution](#contribution)

---

## Vue d'Ensemble

Ce dossier contient **toute la documentation** pour transformer CSWeb en une plateforme communautaire moderne, facile à déployer et à utiliser.

### Objectifs du Projet

✅ **Démocratiser CSWeb** - Setup en 5 minutes au lieu de 2-3 jours
✅ **Breakout sélectif** - Par dictionnaire (déjà implémenté par Assietou Diagne)
✅ **Multi-SGBD** - PostgreSQL, MySQL, SQL Server
✅ **UI moderne** - Admin Panel React + Tailwind
✅ **Documentation exhaustive** - FR + EN + Vidéos
✅ **Communauté active** - Discord + GitHub + YouTube

---

## Structure de la Documentation

```
docs/
├── README.md                              ← Vous êtes ici
│
├── CSWEB-COMMUNITY-PLATFORM-PLAN.md      ← 📘 Plan stratégique complet (500+ lignes)
│   ├── Vision et objectifs
│   ├── État des lieux (CSWeb 8 PG + Kairos API)
│   ├── Architecture proposée
│   ├── Fonctionnalités clés
│   ├── Stack technique
│   ├── Plan de développement (8 semaines)
│   ├── Roadmap v1.0 → v2.5
│   └── Exemples de code (Docker, API, Scheduler)
│
├── CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md   ← 🔗 Pont Kairos → CSWeb (450+ lignes)
│   ├── Code réutilisable (90% du backend)
│   ├── Webhooks PHP (100% réutilisables)
│   ├── Patterns Scheduler, Logs, API
│   ├── Tests à porter (PHPUnit)
│   ├── Checklist migration complète
│   └── Gains estimés (63% temps économisé)
│
└── api-integration/                       ← 🔌 Documentation Webhooks CSWeb
    ├── INDEX.md                           ← Navigation entre tous les docs
    │
    ├── CSWEB-WEBHOOKS-GUIDE.md            ← 📘 Guide complet (60 pages)
    │   ├── Architecture (Frontend → Kairos → CSWeb)
    │   ├── Les 3 webhooks PHP détaillés
    │   ├── Déploiement serveur CSWeb
    │   ├── Sécurité et authentification
    │   ├── Monitoring et logs
    │   ├── Troubleshooting complet
    │   └── 20+ exemples (curl, JavaScript, bash)
    │
    ├── CSWEB-QUICK-REFERENCE.md           ← ⚡ Référence rapide (10 pages)
    │   ├── Commandes curl essentielles
    │   ├── Authentification (JWT + Bearer)
    │   ├── Gestion dictionnaires, breakout, scheduler, logs
    │   ├── Diagnostic et maintenance
    │   └── Workflow typique complet
    │
    ├── api-cspro-breakout.md              ← 🖥️ Référence API Frontend
    │   ├── Endpoints REST Kairos
    │   ├── Formats JSON
    │   └── Workflow intégration
    │
    └── csweb-webhook/                     ← 🛠️ Scripts PHP + Docs
        ├── README.md                      ← Documentation scripts PHP
        ├── breakout-webhook.php           ← Webhook 1: Breakout
        ├── log-reader-webhook.php         ← Webhook 2: Logs
        └── dictionary-schema-webhook.php  ← Webhook 3: Schémas
```

---

## Documentation Technique

### 1. Plan Stratégique Complet

📄 **[CSWEB-COMMUNITY-PLATFORM-PLAN.md](CSWEB-COMMUNITY-PLATFORM-PLAN.md)** (500+ lignes)

**Contenu:**
- Vision et objectifs (court/moyen/long terme)
- État des lieux (CSWeb 8 PG + Kairos API)
- Architecture proposée (diagrammes, composants)
- Fonctionnalités clés (breakout, scheduler, logs, UI)
- Stack technique détaillée (Backend, Frontend, DevOps)
- Plan de développement (8 semaines, 6 phases)
- Documentation & Communauté (Docusaurus, YouTube, Discord)
- Roadmap v1.0 → v2.5 (jusqu'à SaaS 2027)
- **Exemples complets** (Docker Compose, .env, API, Scheduler)

**Durée de lecture:** 45 minutes

**À lire:** Pour comprendre le projet dans son ensemble

---

### 2. Pont Kairos API → CSWeb Community

📄 **[CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md](CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md)** (450+ lignes)

**Contenu:**
- Cartographie complète de la réutilisation du code Kairos
- Webhooks PHP : 100% réutilisables
- API REST : 90% portage Java → PHP
- Scheduler : Pattern complet réutilisable
- Logs Parsing : Regex + code 100% portables
- Documentation : 87% réutilisable (210 pages Kairos)
- Tests : Exemples PHPUnit portés depuis JUnit
- Commandes Symfony : Breakout, Scheduler, Sync
- Supervisor config : Scheduler background
- Checklist migration : 5 phases détaillées
- **Gains estimés : 63% temps économisé** (4.5 semaines vs 12)

**Durée de lecture:** 40 minutes

**À lire:** Pour comprendre comment réutiliser le code Kairos

---

### 3. Documentation Webhooks CSWeb

📁 **[api-integration/](api-integration/)** (4 documents, 90+ pages)

#### 3.1 Guide Complet Webhooks

📄 **[api-integration/CSWEB-WEBHOOKS-GUIDE.md](api-integration/CSWEB-WEBHOOKS-GUIDE.md)** (60 pages)

**Sections:**
1. Architecture Globale (Frontend ↔ Kairos ↔ CSWeb)
2. Les 3 Webhooks CSWeb (breakout, log-reader, schema)
3. Déploiement sur le Serveur CSWeb
4. Gestion à Distance depuis Kairos API
5. Sécurité et Authentification (Bearer Token + JWT)
6. Monitoring et Logs (parsing Symfony)
7. Troubleshooting Complet
8. 20+ Exemples d'Utilisation (curl, JavaScript, bash)

**Durée:** 45 minutes

**Réutilisation CSWeb Community:** 87% (Sections 2, 3, 5, 7 = 100%)

---

#### 3.2 Référence Rapide

📄 **[api-integration/CSWEB-QUICK-REFERENCE.md](api-integration/CSWEB-QUICK-REFERENCE.md)** (10 pages)

**Sections:**
- Authentification (JWT + Bearer Token)
- Dictionnaires CSPro
- Breakout
- Scheduler (gestion jobs)
- Logs CSWeb
- Schémas Dictionnaires
- Diagnostic & Maintenance
- Workflow typique complet

**Durée:** 10 minutes

**Réutilisation CSWeb Community:** 95% (changer URLs seulement)

**Idéal pour:** Administration quotidienne, commandes rapides

---

#### 3.3 Scripts PHP Webhooks

📁 **[api-integration/csweb-webhook/](api-integration/csweb-webhook/)** (3 scripts + README)

**Fichiers:**
1. `breakout-webhook.php` - Exécute breakout CSPro
2. `log-reader-webhook.php` - Lecture logs Symfony
3. `dictionary-schema-webhook.php` - Gestion schémas MySQL/PostgreSQL
4. `README.md` - Documentation scripts

**Réutilisation CSWeb Community:** 100% (copier-coller)

**Déploiement:** `/var/www/csweb/api/*.php`

---

#### 3.4 Index de Navigation

📄 **[api-integration/INDEX.md](api-integration/INDEX.md)** (12 pages)

**Contenu:**
- Navigation rapide entre tous les docs
- Parcours de lecture par rôle (Admin, Dev Backend/Frontend, PM)
- Cas d'usage avec commandes directes
- Support rapide erreurs fréquentes
- Structure des fichiers
- Vue d'ensemble webhooks

**Durée:** Référence (navigation)

---

## Guides de Démarrage

### Pour Démarrer (5 minutes)

1. **Lire** : [api-integration/CSWEB-QUICK-REFERENCE.md](api-integration/CSWEB-QUICK-REFERENCE.md)
2. **Tester** : Commandes curl essentielles
3. **Explorer** : [api-integration/INDEX.md](api-integration/INDEX.md)

### Pour Comprendre le Projet (45 minutes)

1. **Lire** : [CSWEB-COMMUNITY-PLATFORM-PLAN.md](CSWEB-COMMUNITY-PLATFORM-PLAN.md)
2. **Architecture** : Diagrammes et composants
3. **Roadmap** : v1.0 → v2.5

### Pour Développer (2 heures)

1. **Lire** : [CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md](CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md)
2. **Réutiliser** : Code Kairos (Webhooks, Scheduler, Logs)
3. **Tester** : Exemples PHPUnit
4. **Déployer** : Docker Compose

### Pour Administrer (30 minutes)

1. **Lire** : [api-integration/CSWEB-WEBHOOKS-GUIDE.md](api-integration/CSWEB-WEBHOOKS-GUIDE.md)
2. **Configurer** : Webhooks, Scheduler, Logs
3. **Troubleshoot** : Section 7 du guide

---

## Commandes CSWeb Essentielles

### Breakout par Dictionnaire

```bash
# Commande principale (déjà implémentée par Assietou Diagne)
php bin/console csweb:process-cases-by-dict <DICTIONARY>

# Exemple
php bin/console csweb:process-cases-by-dict EVAL_PRODUCTEURS_USAID
```

**Fichiers modifiés** (par Assietou Diagne) :
- `src/AppBundle/CSPro/DictionarySchemaHelper.php`
- `src/AppBundle/Service/DataSettings.php`
- `src/AppBundle/Repository/MapDataRepository.php`

**Documentation détaillée :** Voir `DOC-20251121-WA0004.pdf`

### Webhooks (via curl)

```bash
# Déclencher breakout via webhook
curl -X POST http://193.203.15.16/kairos/breakout-webhook.php \
  -H "Authorization: Bearer kairos_breakout_2024" \
  -H "Content-Type: application/json" \
  -d '{"dictionary":"EVAL_PRODUCTEURS_USAID"}'

# Lire logs
curl "http://193.203.15.16/kairos/log-reader-webhook.php?file=ui.log&lines=200" \
  -H "Authorization: Bearer kairos_breakout_2024"

# Lister dictionnaires
curl "http://193.203.15.16/kairos/dictionary-schema-webhook.php?action=list" \
  -H "Authorization: Bearer kairos_breakout_2024"
```

**Référence complète :** [api-integration/CSWEB-QUICK-REFERENCE.md](api-integration/CSWEB-QUICK-REFERENCE.md)

---

## Contribution

### Structure du Projet CSWeb 8 PG

```
csweb8_pg/
├── src/AppBundle/              # Code métier Symfony
│   ├── Command/                # Commandes console (breakout)
│   ├── Controller/             # Contrôleurs web
│   ├── CSPro/                  # Logique CSPro/CSWeb
│   │   └── DictionarySchemaHelper.php  ← Modifié (Assietou)
│   ├── Repository/             # Accès données
│   │   └── MapDataRepository.php       ← Modifié (Assietou)
│   └── Service/                # Services
│       └── DataSettings.php            ← Modifié (Assietou)
├── app/config/                 # Configuration Symfony
├── web/                        # Assets publics
├── var/logs/                   # Logs Symfony
├── bin/console                 # CLI Symfony
├── breakout-webhook.php        # Webhook breakout (Kairos)
├── log-reader-webhook.php      # Webhook logs (Kairos)
├── dictionary-schema-webhook.php # Webhook schémas (Kairos)
├── docs/                       # Documentation complète ← Vous êtes ici
│   ├── README.md               ← Ce fichier
│   ├── CSWEB-COMMUNITY-PLATFORM-PLAN.md
│   ├── CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md
│   └── api-integration/        # Docs webhooks
├── DOC-20251121-WA0004.pdf     # Doc Assietou (breakout sélectif)
└── README.md                   # README principal CSWeb
```

### Comment Contribuer

1. **Lire la documentation** (ce dossier `docs/`)
2. **Tester localement** (Docker recommandé)
3. **Signaler bugs/features** (GitHub Issues)
4. **Soumettre PR** (vers branch `develop`)
5. **Améliorer docs** (PR sur `docs/`)

### Contacts

**Projet CSWeb Community:**
- GitHub: https://github.com/bounadrame/csweb-community (à venir)
- Discord: https://discord.gg/csweb-community (à venir)
- Email: bdrame@statinfo.sn

**Contributeurs:**
- Bouna DRAME - Lead Developer, Documentation
- Assietou Diagne (ANSD) - Breakout sélectif, PostgreSQL support

---

## Statistiques Documentation

| Catégorie | Nombre Docs | Pages Totales |
|-----------|-------------|---------------|
| Plan Stratégique | 1 doc | ~60 pages |
| Pont Kairos → CSWeb | 1 doc | ~50 pages |
| Webhooks CSWeb | 4 docs | ~90 pages |
| **TOTAL** | **6 documents** | **~200 pages** |

---

## Ressources Externes

**CSPro/CSWeb:**
- https://www.census.gov/data/software/cspro.html
- https://www.csprousers.org/help/CSWeb/
- https://www.csprousers.org/forum/

**Inspiration:**
- Docker CSWeb: https://github.com/csprousers/docker-csweb
- Kairos API: Projet source des webhooks et patterns

**Projet Portfolio:**
- https://bounadrame.github.io/portfolio/

---

## Changelog

### Version 1.0 (Mars 2026)

**Ajouté:**
- ✅ Plan stratégique complet (500+ lignes)
- ✅ Pont Kairos → CSWeb (450+ lignes)
- ✅ Documentation webhooks complète (4 docs, 90 pages)
- ✅ Exemples de code (Docker, API, Scheduler)
- ✅ Roadmap v1.0 → v2.5

**Contributeurs:**
- Bouna DRAME
- Assietou Diagne (ANSD)

---

**Dernière mise à jour:** 14 Mars 2026
**Version:** 1.0
**License:** MIT

---

<div align="center">

**[⬆ Retour en haut](#documentation-csweb-community-platform)**

**Si cette documentation vous a aidé, mettez une ⭐ sur GitHub !**

</div>
