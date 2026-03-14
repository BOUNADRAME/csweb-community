# 📚 Index Complet de la Documentation

> Guide de navigation dans toute la documentation CSWeb Community Platform

**Date:** 14 Mars 2026
**Version:** 1.0

---

## 🎯 Par Où Commencer ?

### Vous êtes...

#### 👤 **Nouvel Utilisateur** (Débutant)
1. ✅ [GETTING-STARTED.md](GETTING-STARTED.md) **(15 min)**
2. ✅ [README-COMMUNITY.md](README-COMMUNITY.md) **(10 min)**
3. ✅ [docs/api-integration/CSWEB-QUICK-REFERENCE.md](docs/api-integration/CSWEB-QUICK-REFERENCE.md) **(10 min)**

**Temps total:** 35 minutes

---

#### 👨‍💻 **Développeur** (Contribution)
1. ✅ [docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md](docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md) **(45 min)**
2. ✅ [docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md](docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md) **(40 min)**
3. ✅ [docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md) **(45 min)**

**Temps total:** 2h 10min

---

#### 🔧 **Administrateur Système** (Déploiement)
1. ✅ [GETTING-STARTED.md](GETTING-STARTED.md) **(15 min)**
2. ✅ [docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md) **(45 min)**
3. ✅ [docs/api-integration/CSWEB-QUICK-REFERENCE.md](docs/api-integration/CSWEB-QUICK-REFERENCE.md) **(10 min)**
4. ✅ `.env.example` **(5 min)** - Configuration variables

**Temps total:** 1h 15min

---

#### 📊 **Chef de Projet / Product Owner** (Vision)
1. ✅ [README-COMMUNITY.md](README-COMMUNITY.md) **(10 min)**
2. ✅ [docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md](docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md) - Sections 1, 8 **(20 min)**
3. ✅ [docs/README.md](docs/README.md) **(5 min)**

**Temps total:** 35 minutes

---

## 📁 Structure Complète

```
csweb8_pg/
│
├── 📄 README.md                          ← README original CSWeb
├── 📄 README-COMMUNITY.md                ← 🆕 README projet communautaire
├── 📄 GETTING-STARTED.md                 ← 🆕 Guide démarrage rapide
├── 📄 DOCUMENTATION-INDEX.md             ← 🆕 Ce fichier (navigation)
├── 📄 .env.example                       ← 🆕 Variables d'environnement
│
├── 📁 docs/                              ← 🆕 Documentation complète
│   │
│   ├── 📄 README.md                      ← Index documentation
│   │
│   ├── 📘 CSWEB-COMMUNITY-PLATFORM-PLAN.md   (500+ lignes, 60 pages)
│   │   ├── Vision et objectifs
│   │   ├── État des lieux (CSWeb 8 PG + Kairos API)
│   │   ├── Architecture proposée
│   │   ├── Fonctionnalités clés
│   │   ├── Stack technique
│   │   ├── Plan de développement (8 semaines)
│   │   ├── Roadmap v1.0 → v2.5
│   │   └── Exemples de code complets
│   │
│   ├── 🔗 CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md   (450+ lignes, 50 pages)
│   │   ├── Code réutilisable (90% backend)
│   │   ├── Webhooks PHP (100% réutilisables)
│   │   ├── Patterns Scheduler, Logs, API
│   │   ├── Tests à porter (PHPUnit)
│   │   ├── Checklist migration complète
│   │   └── Gains estimés (63% temps économisé)
│   │
│   └── 📁 api-integration/               ← Documentation Webhooks CSWeb
│       │
│       ├── 📄 INDEX.md                   ← Navigation docs webhooks
│       │
│       ├── 📘 CSWEB-WEBHOOKS-GUIDE.md    (60 pages)
│       │   ├── 1. Architecture Globale
│       │   ├── 2. Les 3 Webhooks CSWeb
│       │   ├── 3. Déploiement sur Serveur CSWeb
│       │   ├── 4. Gestion à Distance depuis Kairos API
│       │   ├── 5. Sécurité et Authentification
│       │   ├── 6. Monitoring et Logs
│       │   ├── 7. Troubleshooting
│       │   └── 8. Exemples d'Utilisation (20+)
│       │
│       ├── ⚡ CSWEB-QUICK-REFERENCE.md   (10 pages)
│       │   ├── Authentification (JWT + Bearer)
│       │   ├── Dictionnaires CSPro
│       │   ├── Breakout
│       │   ├── Scheduler
│       │   ├── Logs CSWeb
│       │   ├── Schémas Dictionnaires
│       │   ├── Diagnostic & Maintenance
│       │   └── Workflow typique complet
│       │
│       ├── 🖥️ api-cspro-breakout.md      (10 pages)
│       │   └── Référence API Frontend
│       │
│       └── 📁 csweb-webhook/             ← Scripts PHP
│           ├── 📄 README.md              ← Doc scripts PHP
│           ├── breakout-webhook.php      ← Webhook 1: Breakout
│           ├── log-reader-webhook.php    ← Webhook 2: Logs
│           └── dictionary-schema-webhook.php  ← Webhook 3: Schémas
│
├── 📄 DOC-20251121-WA0004.pdf            ← Doc Assietou (breakout sélectif)
│
├── 📁 src/AppBundle/                     ← Code source
│   ├── CSPro/DictionarySchemaHelper.php  ← Modifié (Assietou)
│   ├── Service/DataSettings.php          ← Modifié (Assietou)
│   └── Repository/MapDataRepository.php  ← Modifié (Assietou)
│
└── 📁 (autres fichiers CSWeb...)
```

---

## 📖 Documents par Catégorie

### 🚀 Démarrage

| Document | Pages | Temps | Public |
|----------|-------|-------|--------|
| [GETTING-STARTED.md](GETTING-STARTED.md) | 15 | 15 min | Tous |
| [README-COMMUNITY.md](README-COMMUNITY.md) | 18 | 10 min | Tous |
| [.env.example](.env.example) | 5 | 5 min | DevOps |

**Total:** 38 pages, 30 minutes

---

### 📘 Planification & Vision

| Document | Pages | Temps | Public |
|----------|-------|-------|--------|
| [docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md](docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md) | 60 | 45 min | Tous |
| [docs/README.md](docs/README.md) | 8 | 5 min | Tous |

**Total:** 68 pages, 50 minutes

---

### 🔗 Migration & Réutilisation

| Document | Pages | Temps | Public |
|----------|-------|-------|--------|
| [docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md](docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md) | 50 | 40 min | Développeurs |

**Total:** 50 pages, 40 minutes

---

### 🔌 Webhooks & API

| Document | Pages | Temps | Public |
|----------|-------|-------|--------|
| [docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md) | 60 | 45 min | Admins, Devs |
| [docs/api-integration/CSWEB-QUICK-REFERENCE.md](docs/api-integration/CSWEB-QUICK-REFERENCE.md) | 10 | 10 min | Admins |
| [docs/api-integration/api-cspro-breakout.md](docs/api-integration/api-cspro-breakout.md) | 10 | 10 min | Frontend Devs |
| [docs/api-integration/csweb-webhook/README.md](docs/api-integration/csweb-webhook/README.md) | 7 | 5 min | Admins |
| [docs/api-integration/INDEX.md](docs/api-integration/INDEX.md) | 12 | 5 min | Navigation |

**Total:** 99 pages, 1h 15min

---

### 📊 Statistiques Totales

| Catégorie | Nombre Docs | Pages Totales | Temps Lecture |
|-----------|-------------|---------------|---------------|
| Démarrage | 3 docs | 38 pages | 30 min |
| Planification | 2 docs | 68 pages | 50 min |
| Migration | 1 doc | 50 pages | 40 min |
| Webhooks/API | 5 docs | 99 pages | 1h 15min |
| **TOTAL** | **11 documents** | **~255 pages** | **~3h 15min** |

**+ 3 scripts PHP** (breakout, log-reader, dictionary-schema)

---

## 🎯 Par Cas d'Usage

### Je veux installer CSWeb Community

1. ✅ [GETTING-STARTED.md](GETTING-STARTED.md) - Installation complète
2. ✅ [.env.example](.env.example) - Configuration variables
3. ✅ [docs/api-integration/CSWEB-QUICK-REFERENCE.md](docs/api-integration/CSWEB-QUICK-REFERENCE.md) - Commandes rapides

**Commande:**
```bash
cp .env.example .env
# Éditer .env
docker-compose up -d
```

---

### Je veux comprendre le breakout sélectif

1. ✅ [DOC-20251121-WA0004.pdf](DOC-20251121-WA0004.pdf) - Doc Assietou (modifications techniques)
2. ✅ [docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md) - Section 2.1
3. ✅ [docs/api-integration/CSWEB-QUICK-REFERENCE.md](docs/api-integration/CSWEB-QUICK-REFERENCE.md) - Section Breakout

**Commande:**
```bash
php bin/console csweb:process-cases-by-dict <DICTIONARY>
```

**Fichiers modifiés (Assietou):**
- `src/AppBundle/CSPro/DictionarySchemaHelper.php`
- `src/AppBundle/Service/DataSettings.php`
- `src/AppBundle/Repository/MapDataRepository.php`

---

### Je veux déployer les webhooks

1. ✅ [docs/api-integration/csweb-webhook/README.md](docs/api-integration/csweb-webhook/README.md) - Installation
2. ✅ [docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md) - Section 3
3. ✅ [.env.example](.env.example) - Variable `WEBHOOK_TOKEN`

**Fichiers à copier:**
- `breakout-webhook.php` → `/var/www/csweb/api/`
- `log-reader-webhook.php` → `/var/www/csweb/api/`
- `dictionary-schema-webhook.php` → `/var/www/csweb/api/`

---

### Je veux contribuer au code

1. ✅ [docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md](docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md) - Comprendre le projet
2. ✅ [docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md](docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md) - Code réutilisable
3. ✅ [docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md) - Patterns

**Checklist:**
- [ ] Lire plan stratégique
- [ ] Setup environnement dev (Docker)
- [ ] Comprendre architecture
- [ ] Tester localement
- [ ] Soumettre PR

---

### Je veux configurer le scheduler

1. ✅ [docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md](docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md) - Section 4.2
2. ✅ [docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md](docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md) - Section 4.1.C
3. ✅ [docs/api-integration/CSWEB-QUICK-REFERENCE.md](docs/api-integration/CSWEB-QUICK-REFERENCE.md) - Section Scheduler

**Exemple:**
```bash
curl -X PATCH http://localhost:8080/api/scheduler/jobs/BREAKOUT_EVAL_PRODUCTEURS_USAID \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"cronExpression":"0 0 1 * * ?","enabled":true}'
```

---

### Je veux monitorer les logs

1. ✅ [docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md) - Section 6
2. ✅ [docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md](docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md) - Section 4.1.D
3. ✅ [docs/api-integration/CSWEB-QUICK-REFERENCE.md](docs/api-integration/CSWEB-QUICK-REFERENCE.md) - Section Logs

**Exemple:**
```bash
curl "http://localhost:8080/api/logs?file=ui.log&lines=200&level=ERROR" \
  -H "Authorization: Bearer $TOKEN"
```

---

### Je veux comprendre l'architecture multi-SGBD

1. ✅ [docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md](docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md) - Section 3.3
2. ✅ [docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md) - Section 2.3
3. ✅ [docs/api-integration/CSWEB-QUICK-REFERENCE.md](docs/api-integration/CSWEB-QUICK-REFERENCE.md) - Section Schémas

**SGBD supportés:**
- ✅ PostgreSQL (recommandé pour analytics)
- ✅ MySQL (compatible, legacy)
- ✅ SQL Server (écosystème Microsoft)

---

## 🔍 Recherche Rapide

### Mots-Clés

| Cherchez... | Document | Section |
|-------------|----------|---------|
| **Installation** | [GETTING-STARTED.md](GETTING-STARTED.md) | Étape 1-3 |
| **Docker** | [GETTING-STARTED.md](GETTING-STARTED.md) | Prérequis |
| **Breakout sélectif** | [DOC-20251121-WA0004.pdf](DOC-20251121-WA0004.pdf) | Tout |
| **Webhooks** | [docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md) | Section 2 |
| **Scheduler** | [docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md](docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md) | Section 4.2 |
| **Logs** | [docs/api-integration/CSWEB-QUICK-REFERENCE.md](docs/api-integration/CSWEB-QUICK-REFERENCE.md) | Section Logs |
| **PostgreSQL** | [.env.example](.env.example) | Section PostgreSQL |
| **API** | [docs/api-integration/api-cspro-breakout.md](docs/api-integration/api-cspro-breakout.md) | Tout |
| **Sécurité** | [docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md) | Section 5 |
| **Troubleshooting** | [docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md](docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md) | Section 7 |

---

## 📞 Support

### Documentation Manquante ?

Si vous cherchez une information qui n'est pas dans la documentation :

1. Vérifiez [docs/api-integration/INDEX.md](docs/api-integration/INDEX.md) - Index complet webhooks
2. Cherchez dans [docs/README.md](docs/README.md) - Index documentation
3. Ouvrez une issue GitHub (à venir)
4. Contactez : bdrame@statinfo.sn

### Améliorer la Documentation

Contributions bienvenues !

```bash
# Fork le repo
git clone https://github.com/bounadrame/csweb-community.git

# Modifier les docs
cd docs/
# ... vos modifications ...

# Soumettre PR
git add .
git commit -m "docs: amélioration section X"
git push origin feature/improve-docs
# → Créer PR sur GitHub
```

---

## ✅ Checklist Lecture

Cochez au fur et à mesure :

### Débutant
- [ ] README-COMMUNITY.md
- [ ] GETTING-STARTED.md
- [ ] docs/api-integration/CSWEB-QUICK-REFERENCE.md

### Intermédiaire
- [ ] docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md
- [ ] docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md
- [ ] .env.example

### Avancé
- [ ] docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md
- [ ] DOC-20251121-WA0004.pdf (Assietou)
- [ ] Tous les docs api-integration/

---

## 🎓 Parcours de Formation Recommandé

### Jour 1: Découverte (2h)
1. README-COMMUNITY.md (10 min)
2. GETTING-STARTED.md (15 min)
3. Installation Docker (30 min)
4. Premier breakout (30 min)
5. Exploration interface (35 min)

### Jour 2: Approfondissement (3h)
1. docs/CSWEB-COMMUNITY-PLATFORM-PLAN.md (45 min)
2. Configuration multi-SGBD (30 min)
3. Scheduler web UI (30 min)
4. Monitoring logs (30 min)
5. Troubleshooting (45 min)

### Jour 3: Maîtrise (4h)
1. docs/CSWEB-BRIDGE-KAIROS-TO-COMMUNITY.md (40 min)
2. docs/api-integration/CSWEB-WEBHOOKS-GUIDE.md (45 min)
3. Déploiement webhooks (60 min)
4. Tests et validation (60 min)
5. Documentation projet (35 min)

**Total: 9 heures de formation complète**

---

## 🗺️ Roadmap Documentation

### v1.0 (Actuel) ✅
- ✅ Plan stratégique complet
- ✅ Pont Kairos → CSWeb
- ✅ Documentation webhooks (4 docs)
- ✅ Guide démarrage rapide
- ✅ Variables d'environnement

### v1.1 (À venir)
- [ ] DEPLOYMENT-GUIDE.md (déploiement production)
- [ ] CONTRIBUTING.md (guide contribution)
- [ ] FAQ.md (questions fréquentes)
- [ ] Tutoriels vidéo YouTube (12 vidéos)

### v1.2 (À venir)
- [ ] API-COMPLETE-REFERENCE.md (OpenAPI/Swagger)
- [ ] TESTING-GUIDE.md (tests unitaires/intégration)
- [ ] PERFORMANCE-GUIDE.md (optimisations)
- [ ] SECURITY-GUIDE.md (audit sécurité)

---

**Dernière mise à jour:** 14 Mars 2026
**Version:** 1.0
**Mainteneur:** Bouna DRAME

---

<div align="center">

**[⬆ Retour en haut](#-index-complet-de-la-documentation)**

**Documenté avec ❤️ par la communauté CSWeb**

</div>
