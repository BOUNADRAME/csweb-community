# CSWeb Community Platform - Plan Stratégique Complet

> Démocratiser CSWeb pour les instituts statistiques africains

**Date:** 14 Mars 2026
**Auteur:** Bouna DRAME
**Contributrice:** Assietou Diagne (ANSD)

---

## 📋 Table des Matières

1. [Vision et Objectifs](#1-vision-et-objectifs)
2. [État des Lieux](#2-état-des-lieux)
3. [Architecture Proposée](#3-architecture-proposée)
4. [Fonctionnalités Clés](#4-fonctionnalités-clés)
5. [Stack Technique](#5-stack-technique)
6. [Plan de Développement](#6-plan-de-développement)
7. [Documentation et Communauté](#7-documentation-et-communauté)
8. [Roadmap](#8-roadmap)
9. [Exemples de Configuration](#9-exemples-de-configuration)

---

## 1. Vision et Objectifs

### 1.1 Vision

**"Rendre CSWeb aussi simple à déployer et configurer que WordPress, tout en restant professionnel et sécurisé."**

### 1.2 Problématique Actuelle

#### Limitations CSWeb Standard

❌ **Breakout global obligatoire** (tous les dictionnaires d'un coup)
❌ **Configuration manuelle crontab** (`crontab -e`)
❌ **Logs difficiles d'accès** (SSH requis)
❌ **Setup complexe** (MySQL, PHP, Apache, permissions)
❌ **Pas de Docker officiel production-ready**
❌ **Documentation limitée** pour non-experts
❌ **Pas de communauté francophone active**

#### Impact

- **Barrière technique** pour statisticiens non-DevOps
- **Temps de setup** : 2-3 jours au lieu de 30 minutes
- **Erreurs fréquentes** : permissions, crontab, breakout
- **Maintenance difficile** : pas d'UI pour monitoring

### 1.3 Objectifs du Projet

#### Court Terme (3 mois)

✅ **Docker production-ready** : Déploiement en 1 commande
✅ **Breakout sélectif** : Par dictionnaire (déjà fait par Assietou !)
✅ **UI de configuration** : Jobs, logs, schémas sans SSH
✅ **Documentation FR/EN** : Guide complet + tutoriels vidéo
✅ **Site GitHub Pages** : Blog + démos interactives

#### Moyen Terme (6 mois)

✅ **Multi-SGBD** : PostgreSQL, MySQL, SQL Server
✅ **Scheduler Web** : Gestion jobs breakout via interface
✅ **Monitoring intégré** : Dashboard métriques + logs
✅ **API REST** : Automation et intégration externe
✅ **Communauté active** : Forum + Slack + Contributions

#### Long Terme (12 mois)

✅ **Écosystème complet** : Plugins, templates, marketplace
✅ **HA/Scalabilité** : Support multi-serveurs
✅ **SaaS optionnel** : Offre hébergée pour petits INS
✅ **Certification** : Formation officielle CSWeb Community

---

## 2. État des Lieux

### 2.1 Projet CSWeb 8 PostgreSQL

**Repository:** `/Users/bdrame/Developer/opensource/csweb8-pg-oepnsource-contribor/csweb8_pg`
**Branch:** `master` (à jour)
**État:** ✅ Fonctionnel avec breakout par dictionnaire

#### Modifications Clés (par Assietou Diagne)

Basé sur le PDF `DOC-20251121-WA0004.pdf`, les fichiers modifiés :

##### A. Intégration PDO PostgreSQL

**Fichiers modifiés :**
1. `src/AppBundle/CSPro/DictionarySchemaHelper.php`
2. `src/AppBundle/Service/DataSettings.php`
3. `src/AppBundle/Repository/MapDataRepository.php`

**Objectif :** Support PostgreSQL en plus de MySQL

##### B. Mise à Jour Base de Données

**Changement SQL :**
```sql
ALTER TABLE `cspro_dictionaries_schema`
DROP INDEX `schema_name`;
```

**Objectif :** Permettre plusieurs dictionnaires sur même schéma (table différente)

##### C. Transformation des Scripts

###### 1. `cleanDictionarySchema()` - Nettoyage Tables

**Avant (MySQL uniquement) :**
```php
// Supprime TOUTES les tables du schéma
$sql = 'DROP TABLE ' . $table->getName() . ' CASCADE';
```

**Après (Sélectif par dictionnaire) :**
```php
// Supprime uniquement les tables du dictionnaire spécifique
$dictionaryLabel = str_replace(" ", "_", $this->dictionary->getName());
$mystring = strtolower($dictionaryLabel)."_";

foreach ($tables as $table) {
    if(substr($table->getName(), 0, strlen($mystring)) === $mystring){
        $sql = 'DROP TABLE "' . $table->getName() . '" CASCADE';
        $this->conn->prepare($sql)->execute();
    }
}
```

**Impact :** ✅ Breakout sélectif par dictionnaire activé

###### 2. `createDictionarySchema()` - Création Tables

**Changement principal :**
```php
// AVANT: 1 seule requête SQL globale
$this->conn->prepare($dictionarySQL)->execute();

// APRÈS: Explode + boucle pour PostgreSQL
$explodedDictionarySQL = implode(";" . PHP_EOL, $dictionarySQL);
foreach($dictionarySQL AS $oneDictionarySQL){
    $this->conn->prepare($oneDictionarySQL)->execute();
}
```

**Objectif :** Compatibilité PostgreSQL (n'accepte pas multiples requêtes)

###### 3. `generateDictionary()` - Génération Schéma

**Changement :**
```php
// Normalisation nom schéma
$this->nomSchema = str_replace(" ", "_",
    str_replace("_DICT", "", $dictionary->getName()));
```

**Objectif :** Nom de schéma propre et compatible multi-DB

###### 4. `createDefaultTables()` - Tables Par Défaut

**Ajout support PostgreSQL :**
- Types de données adaptés (TEXT vs VARCHAR)
- Gestion des séquences (SERIAL vs AUTO_INCREMENT)
- Contraintes et index PostgreSQL

#### Architecture Actuelle

```
csweb8_pg/
├── src/AppBundle/          # Code métier Symfony
│   ├── Command/            # Commandes console (breakout)
│   ├── Controller/         # Contrôleurs web
│   ├── CSPro/              # Logique CSPro/CSWeb
│   │   └── DictionarySchemaHelper.php  ← Modifié
│   ├── Repository/         # Accès données
│   │   └── MapDataRepository.php       ← Modifié
│   └── Service/            # Services
│       └── DataSettings.php            ← Modifié
├── app/config/             # Configuration Symfony
├── web/                    # Assets publics
├── var/logs/               # Logs Symfony
├── bin/console             # CLI Symfony
└── breakout-webhook.php    # Webhook Kairos (ajouté)
```

### 2.2 Projet Kairos API (Expérience Acquise)

**Leçons clés à réutiliser :**

✅ **Webhooks sécurisés** : Bearer Token + JWT
✅ **Scheduler dynamique** : Jobs configurables sans crontab
✅ **Parsing logs Symfony** : Interface web au lieu de SSH
✅ **API REST complète** : Automation + intégration
✅ **Documentation exhaustive** : 210+ pages, 20 documents
✅ **Docker PostgreSQL** : Setup automatisé

### 2.3 Docker CSWeb Existant

**Repository:** https://github.com/csprousers/docker-csweb

#### Points Positifs

✅ Image Docker fonctionnelle
✅ Base MySQL incluse
✅ Apache configuré

#### Limitations

❌ Basique (pas de scheduler, monitoring)
❌ MySQL seulement (pas PostgreSQL/SQL Server)
❌ Configuration complexe (volumes, env vars)
❌ Pas de hot-reload config
❌ Documentation minimale

---

## 3. Architecture Proposée

### 3.1 Vue d'Ensemble

```
┌────────────────────────────────────────────────────────────────────┐
│                    CSWeb Community Platform                        │
│                                                                    │
│  ┌────────────────┐   ┌────────────────┐   ┌────────────────┐   │
│  │  CSWeb Core    │   │  Admin Panel   │   │  API Gateway   │   │
│  │  (Symfony 5)   │◄──┤  (React/Vue)   │◄──┤  (REST/GraphQL)│   │
│  └────────────────┘   └────────────────┘   └────────────────┘   │
│          │                     │                     │            │
│  ┌────────────────┐   ┌────────────────┐   ┌────────────────┐   │
│  │  Scheduler     │   │  Log Monitor   │   │  Metrics       │   │
│  │  (Jobs Mgmt)   │   │  (Real-time)   │   │  (Grafana)     │   │
│  └────────────────┘   └────────────────┘   └────────────────┘   │
│                                                                    │
├────────────────────────────────────────────────────────────────────┤
│                         Data Layer                                 │
│  ┌────────────────┐   ┌────────────────┐   ┌────────────────┐   │
│  │  CSWeb MySQL   │   │  Breakout PG   │   │  Breakout MSSQL│   │
│  │  (Metadata)    │   │  (Analytics)   │   │  (Option)      │   │
│  └────────────────┘   └────────────────┘   └────────────────┘   │
└────────────────────────────────────────────────────────────────────┘
                                ▲
                                │
                    ┌───────────┴───────────┐
                    │   Docker Compose      │
                    │   (All services)      │
                    └───────────────────────┘
```

### 3.2 Composants

#### 1. CSWeb Core (Existant Amélioré)

- **Base:** Symfony 5.4 + PHP 8.0+
- **Améliorations:**
  - ✅ Breakout sélectif (déjà fait par Assietou)
  - ➕ Support multi-SGBD (PostgreSQL, MySQL, SQL Server)
  - ➕ API REST complète
  - ➕ Scheduler intégré (remplace crontab)

#### 2. Admin Panel (Nouveau)

- **Stack:** React 18 + TypeScript + Tailwind CSS
- **Fonctionnalités:**
  - Dashboard overview (dictionnaires, jobs, métriques)
  - Configuration jobs breakout (cron, activer/désactiver)
  - Monitoring logs en temps réel (SSE)
  - Gestion schémas MySQL/PostgreSQL
  - Upload dictionnaires + preview
  - Gestion utilisateurs/permissions

#### 3. API Gateway (Nouveau)

- **Stack:** Express.js ou Symfony API Platform
- **Endpoints:**
  - `/api/dictionaries` - CRUD dictionnaires
  - `/api/breakout/jobs` - Gestion jobs
  - `/api/breakout/trigger` - Déclencher breakout
  - `/api/logs` - Logs streaming
  - `/api/schemas` - Configuration schémas DB
  - `/api/metrics` - Métriques système

#### 4. Scheduler (Nouveau)

- **Stack:** Node.js + Agenda ou PHP Symfony Schedule
- **Fonctionnalités:**
  - Jobs breakout par dictionnaire
  - Expressions cron configurables via UI
  - Historique exécutions + métriques
  - Retry automatique en cas d'échec
  - Notifications (email, Slack)

#### 5. Log Monitor (Nouveau)

- **Stack:** Monolog + SSE + React
- **Fonctionnalités:**
  - Logs temps réel (WebSocket/SSE)
  - Filtres (niveau, dictionnaire, date)
  - Export CSV/JSON
  - Alertes sur patterns (ERROR, CRITICAL)

#### 6. Metrics (Optionnel)

- **Stack:** Prometheus + Grafana
- **Métriques:**
  - Breakout: durée, erreurs, volumétrie
  - Système: CPU, RAM, disque
  - API: latence, requêtes/s, erreurs

### 3.3 Multi-SGBD Strategy

#### Architecture Base de Données

```
CSWeb Metadata (MySQL)
  ├── cspro_dictionaries          ← Définitions dictionnaires
  ├── cspro_dictionaries_schema   ← Config breakout (host, schema, user)
  ├── cspro_users                 ← Utilisateurs
  └── cspro_cases                 ← Données collecte (brutes)

Breakout Destinations (Multi-SGBD)
  ├── PostgreSQL (Recommandé)     ← Analytics, BI, Data Warehouse
  ├── MySQL (Compatible)          ← Legacy, petits projets
  └── SQL Server (Entreprise)     ← Écosystème Microsoft
```

#### Implémentation

**Table `cspro_dictionaries_schema` étendue :**

```sql
CREATE TABLE cspro_dictionaries_schema (
  dictionary_id INT PRIMARY KEY,
  db_type ENUM('postgresql', 'mysql', 'sqlserver') NOT NULL DEFAULT 'postgresql',
  host_name VARCHAR(255) NOT NULL,
  port INT DEFAULT NULL,  -- NULL = default par SGBD
  schema_name VARCHAR(255) NOT NULL,
  schema_user_name VARCHAR(255) NOT NULL,
  schema_password VARCHAR(255) NOT NULL,
  use_ssl BOOLEAN DEFAULT FALSE,
  connection_options JSON DEFAULT NULL,
  created_time DATETIME NOT NULL,
  modified_time DATETIME NOT NULL
);
```

**Adapters Pattern :**

```php
// src/AppBundle/CSPro/Adapter/DatabaseAdapterInterface.php
interface DatabaseAdapterInterface {
    public function connect(array $config): void;
    public function createSchema(Schema $schema): void;
    public function dropTable(string $tableName): void;
    public function getDataType(string $csproType): string;
}

// Implémentations
class PostgreSQLAdapter implements DatabaseAdapterInterface { ... }
class MySQLAdapter implements DatabaseAdapterInterface { ... }
class SQLServerAdapter implements DatabaseAdapterInterface { ... }
```

**Factory :**

```php
class DatabaseAdapterFactory {
    public static function create(string $dbType): DatabaseAdapterInterface {
        return match($dbType) {
            'postgresql' => new PostgreSQLAdapter(),
            'mysql' => new MySQLAdapter(),
            'sqlserver' => new SQLServerAdapter(),
            default => throw new \InvalidArgumentException("Unsupported DB type: $dbType")
        };
    }
}
```

---

## 4. Fonctionnalités Clés

### 4.1 Breakout Sélectif (✅ Déjà Fait)

**Acquis (Assietou) :**
- ✅ Breakout par dictionnaire spécifique
- ✅ Suppression sélective tables
- ✅ Support PostgreSQL

**À Ajouter :**
- ➕ Interface UI pour lancer breakout
- ➕ Historique des breakouts (date, durée, statut)
- ➕ Preview des données avant breakout
- ➕ Rollback en cas d'erreur

### 4.2 Configuration Jobs (🆕 Nouveau)

#### Interface Web

**Page: Scheduler → Breakout Jobs**

```
╔════════════════════════════════════════════════════════════════╗
║  Breakout Jobs                                       [+ Nouveau] ║
╠════════════════════════════════════════════════════════════════╣
║                                                                  ║
║  📋 EVAL_PRODUCTEURS_USAID                          ⚡ Actif    ║
║  ├─ Cron: 0 0 1 * * * (Tous les jours à 1h)        [Modifier]  ║
║  ├─ Dernière exécution: 14/03/2026 01:00 (Succès)              ║
║  ├─ Durée: 4.5s | Lignes: 1,524                                ║
║  └─ Prochaine exécution: 15/03/2026 01:00          [▶ Lancer]  ║
║                                                                  ║
║  📋 MENAGE_2024                                     ⏸ Pause     ║
║  ├─ Cron: 0 30 2 * * * (Tous les jours à 2h30)     [Modifier]  ║
║  ├─ Dernière exécution: 13/03/2026 02:30 (Échec)               ║
║  ├─ Erreur: Connection timeout                                  ║
║  └─ Prochaine exécution: -                         [▶ Lancer]  ║
║                                                                  ║
╚════════════════════════════════════════════════════════════════╝
```

#### API Backend

**Endpoint:** `POST /api/breakout/jobs`

```json
{
  "dictionaryId": 3,
  "enabled": true,
  "cronExpression": "0 0 1 * * *",
  "targetDatabase": {
    "type": "postgresql",
    "host": "localhost",
    "port": 5432,
    "schema": "kairos_analytics",
    "user": "kairos_user",
    "password": "encrypted_password"
  },
  "notifications": {
    "email": ["admin@example.com"],
    "onSuccess": false,
    "onFailure": true
  }
}
```

### 4.3 Logs Monitoring (🆕 Nouveau)

#### Interface Temps Réel

**Page: Monitoring → Logs**

```
╔════════════════════════════════════════════════════════════════╗
║  Logs CSWeb                                   [📥 Export] [🔄]  ║
╠════════════════════════════════════════════════════════════════╣
║  Filtres: [ERROR ▼] [Dictionnaire: Tous ▼] [Dernières 24h ▼]  ║
╠════════════════════════════════════════════════════════════════╣
║                                                                  ║
║  🔴 2026-03-14 16:50:01 | app.ERROR                             ║
║      Failed deleting tables for dictionary EVAL_PRODUCTEURS     ║
║      Context: SQLSTATE[42P01]: Undefined table                  ║
║      [📋 Copier] [🔗 Contexte]                                   ║
║                                                                  ║
║  🟡 2026-03-14 16:45:32 | console.WARNING                       ║
║      Breakout job taking longer than expected (120s)            ║
║      Dictionary: MENAGE_2024                                     ║
║      [📋 Copier] [🔗 Contexte]                                   ║
║                                                                  ║
║  🟢 2026-03-14 16:30:15 | app.INFO                              ║
║      Breakout completed successfully                             ║
║      Dictionary: EVAL_PRODUCTEURS | Duration: 4523ms            ║
║      [📋 Copier] [🔗 Contexte]                                   ║
║                                                                  ║
╚════════════════════════════════════════════════════════════════╝
```

#### API SSE (Server-Sent Events)

**Endpoint:** `GET /api/logs/stream?level=ERROR&dictionary=EVAL_PRODUCTEURS`

```javascript
// Frontend
const eventSource = new EventSource('/api/logs/stream?level=ERROR');

eventSource.onmessage = (event) => {
  const logEntry = JSON.parse(event.data);
  // {
  //   timestamp: "2026-03-14T16:50:01.266568+00:00",
  //   level: "ERROR",
  //   channel: "app",
  //   message: "Failed deleting tables...",
  //   context: "{\"exception\":\"SQLSTATE[42P01]\"}"
  // }
  appendToLogView(logEntry);
};
```

### 4.4 Dashboard Overview (🆕 Nouveau)

```
╔════════════════════════════════════════════════════════════════╗
║  CSWeb Dashboard                                     👤 Admin   ║
╠════════════════════════════════════════════════════════════════╣
║                                                                  ║
║  📊 Vue d'Ensemble                                               ║
║  ┌──────────────┬──────────────┬──────────────┬──────────────┐ ║
║  │ Dictionnaires│  Jobs Actifs │  Breakouts   │   Erreurs    │ ║
║  │      12      │       8      │   24h: 15    │   24h: 2     │ ║
║  │     (+2)     │      (+1)    │              │              │ ║
║  └──────────────┴──────────────┴──────────────┴──────────────┘ ║
║                                                                  ║
║  📈 Activité Récente                                             ║
║  ┌────────────────────────────────────────────────────────────┐ ║
║  │ [Graphique ligne] Breakouts par jour (7 derniers jours)   │ ║
║  │                                                            │ ║
║  │  15 ┤                                            ╭─╮      │ ║
║  │  10 ┤                      ╭─╮            ╭─╮   │ │      │ ║
║  │   5 ┤         ╭─╮    ╭─╮  │ │      ╭─╮  │ │   │ │      │ ║
║  │   0 ┼─────────┴─┴────┴─┴──┴─┴──────┴─┴──┴─┴───┴─┴──────│ ║
║  │      08  09  10  11  12  13  14                          │ ║
║  └────────────────────────────────────────────────────────────┘ ║
║                                                                  ║
║  ⚡ Jobs en Cours                                                ║
║  • EVAL_PRODUCTEURS_USAID  [████████░░] 80% (2m 15s)           ║
║  • MENAGE_2024             [██░░░░░░░░] 20% (32s)              ║
║                                                                  ║
╚════════════════════════════════════════════════════════════════╝
```

---

## 5. Stack Technique

### 5.1 Backend

| Composant | Technologie | Version | Justification |
|-----------|-------------|---------|---------------|
| **Core** | Symfony | 5.4 LTS | Stable, sécurisé, existant |
| **PHP** | PHP | 8.0+ | Performance, typage, match |
| **DB Metadata** | MySQL | 8.0+ | Standard CSWeb, compatible |
| **DB Analytics** | PostgreSQL | 14+ | Performance, JSON, analytics |
| **ORM** | Doctrine DBAL | 3.5+ | Multi-DB, migrations |
| **Scheduler** | Symfony Schedule | 5.4+ | Natif, pas de dépendance |
| **API** | API Platform | 3.x | REST/GraphQL auto, OpenAPI |
| **Logs** | Monolog | 2.8+ | Standard Symfony |

### 5.2 Frontend

| Composant | Technologie | Version | Justification |
|-----------|-------------|---------|---------------|
| **Framework** | React | 18+ | Écosystème riche, communauté |
| **Language** | TypeScript | 5+ | Type safety, DX |
| **Build** | Vite | 5+ | Fast, moderne |
| **UI Library** | Tailwind CSS | 3+ | Utility-first, rapide |
| **Components** | Radix UI | Latest | Accessible, headless |
| **Forms** | React Hook Form | 7+ | Performance, validation |
| **State** | Zustand | 4+ | Simple, pas de boilerplate |
| **Charts** | Recharts | 2+ | Déclaratif, responsive |
| **API Client** | TanStack Query | 5+ | Cache, sync, DevTools |

### 5.3 DevOps

| Composant | Technologie | Version | Justification |
|-----------|-------------|---------|---------------|
| **Container** | Docker | 24+ | Standard, portable |
| **Orchestration** | Docker Compose | 2.x | Simple, multi-services |
| **Web Server** | Nginx | 1.24+ | Performance, reverse proxy |
| **Process Mgr** | Supervisor | 4.x | Gestion jobs PHP |
| **CI/CD** | GitHub Actions | - | Gratuit, intégré GitHub |
| **Monitoring** | Prometheus | 2.x | Métriques, alertes |
| **Viz** | Grafana | 10+ | Dashboards, alertes |

### 5.4 Documentation

| Type | Technologie | Justification |
|------|-------------|---------------|
| **Site** | Docusaurus | Moderne, React, i18n |
| **Blog** | GitHub Pages | Gratuit, Markdown, Git |
| **API Docs** | OpenAPI/Swagger | Standard, auto-généré |
| **Vidéos** | YouTube | SEO, accessibilité |
| **Chat** | Discord | Communauté, gratuit |

---

## 6. Plan de Développement

### Phase 1: Fondations (Mois 1-2) 🏗️

#### Semaine 1-2: Setup Infrastructure

**Objectifs:**
- [ ] Docker Compose multi-services
- [ ] Nginx reverse proxy
- [ ] PostgreSQL + MySQL containers
- [ ] Scripts init automatiques

**Livrables:**
```bash
git clone https://github.com/bounadrame/csweb-community
cd csweb-community
cp .env.example .env
docker-compose up -d
# → CSWeb accessible sur http://localhost:8080
# → Admin Panel sur http://localhost:3000
```

#### Semaine 3-4: API REST

**Endpoints prioritaires:**
- [ ] `GET /api/dictionaries` - Liste dictionnaires
- [ ] `POST /api/breakout/jobs` - Créer job
- [ ] `PATCH /api/breakout/jobs/:id` - Modifier job
- [ ] `POST /api/breakout/trigger/:dict` - Lancer breakout
- [ ] `GET /api/logs/stream` - SSE logs

**Tests:**
- [ ] Tests unitaires (PHPUnit)
- [ ] Tests intégration (API Platform)
- [ ] Documentation OpenAPI auto-générée

#### Semaine 5-6: Multi-SGBD Support

**Implémentation:**
- [ ] Adapter Pattern (PostgreSQL, MySQL, SQL Server)
- [ ] Factory DatabaseAdapter
- [ ] Tests sur 3 SGBD
- [ ] Migration schéma `cspro_dictionaries_schema`

**Configuration:**
```yaml
# app/config/database_adapters.yml
database_adapters:
  postgresql:
    class: AppBundle\CSPro\Adapter\PostgreSQLAdapter
    default_port: 5432
  mysql:
    class: AppBundle\CSPro\Adapter\MySQLAdapter
    default_port: 3306
  sqlserver:
    class: AppBundle\CSPro\Adapter\SQLServerAdapter
    default_port: 1433
```

#### Semaine 7-8: Scheduler Intégré

**Implémentation:**
- [ ] Service SchedulerManager
- [ ] Table `scheduler_jobs` (pattern Kairos)
- [ ] Cron dynamique (sans crontab -e)
- [ ] Historique exécutions

**Usage:**
```php
// Créer job automatiquement lors upload dictionnaire
$scheduler->createJob([
    'jobId' => 'BREAKOUT_' . $dictionary->getName(),
    'cronExpression' => '0 0 1 * * *', // Tous les jours à 1h
    'enabled' => false, // Désactivé par défaut
    'params' => ['dictionary' => $dictionary->getName()]
]);
```

### Phase 2: Interface Admin (Mois 3-4) 🎨

#### Semaine 9-10: Dashboard

**Pages:**
- [ ] `/dashboard` - Vue d'ensemble
- [ ] `/dictionaries` - Liste + upload
- [ ] `/breakout/jobs` - Gestion jobs

**Composants:**
```tsx
// src/pages/Dashboard.tsx
export function Dashboard() {
  const { data: stats } = useQuery(['stats'], fetchStats);

  return (
    <div className="grid grid-cols-4 gap-4">
      <StatCard title="Dictionnaires" value={stats.dictionaries} />
      <StatCard title="Jobs Actifs" value={stats.activeJobs} />
      <StatCard title="Breakouts 24h" value={stats.breakouts24h} />
      <StatCard title="Erreurs 24h" value={stats.errors24h} />
    </div>
  );
}
```

#### Semaine 11-12: Scheduler UI

**Fonctionnalités:**
- [ ] Liste jobs avec statut (actif/pause)
- [ ] Modal création/édition job
- [ ] Cron expression builder (visuel)
- [ ] Historique exécutions par job
- [ ] Trigger manuel

**Composant Cron Builder:**
```tsx
<CronBuilder
  value="0 0 1 * * *"
  onChange={(cron) => setJobConfig({ ...jobConfig, cron })}
  presets={[
    { label: 'Tous les jours à 1h', value: '0 0 1 * * *' },
    { label: 'Toutes les heures', value: '0 0 * * * *' },
    { label: 'Toutes les 10 minutes', value: '0 */10 * * * *' }
  ]}
/>
```

#### Semaine 13-14: Logs Monitoring

**Features:**
- [ ] Streaming SSE temps réel
- [ ] Filtres (niveau, dictionnaire, date)
- [ ] Auto-scroll + pause
- [ ] Export CSV/JSON
- [ ] Highlight syntax (JSON context)

**Implémentation:**
```tsx
const { data: logs, isConnected } = useLogStream({
  level: 'ERROR',
  dictionary: selectedDict,
  autoScroll: true
});

return (
  <LogViewer
    logs={logs}
    filters={<LogFilters />}
    actions={<LogActions onExport={exportLogs} />}
    isLive={isConnected}
  />
);
```

#### Semaine 15-16: Configuration Multi-DB

**Page: `/schemas/:id/config`**

```tsx
<DatabaseConfig
  dictionaryId={id}
  onSubmit={async (config) => {
    await api.post(`/api/schemas`, {
      dictionaryId: id,
      dbType: config.type, // postgresql | mysql | sqlserver
      hostName: config.host,
      port: config.port,
      schemaName: config.schema,
      schemaUserName: config.user,
      schemaPassword: config.password,
      useSsl: config.ssl
    });
  }}
/>
```

**Form dynamique selon dbType:**
```tsx
{config.type === 'postgresql' && (
  <TextField
    label="Schema"
    placeholder="public"
    helperText="PostgreSQL schema name (default: public)"
  />
)}

{config.type === 'sqlserver' && (
  <TextField
    label="Instance"
    placeholder="MSSQLSERVER"
    helperText="SQL Server instance name"
  />
)}
```

### Phase 3: Documentation (Mois 5) 📚

#### Semaine 17-18: Site Docusaurus

**Structure:**
```
docs/
├── getting-started/
│   ├── introduction.md
│   ├── installation.md
│   ├── docker-setup.md
│   └── first-breakout.md
├── guides/
│   ├── multi-database.md
│   ├── scheduler.md
│   ├── monitoring.md
│   └── api-integration.md
├── api/
│   ├── authentication.md
│   ├── dictionaries.md
│   ├── breakout.md
│   └── logs.md
└── community/
    ├── contributing.md
    ├── code-of-conduct.md
    └── roadmap.md
```

**Configuration i18n:**
```js
// docusaurus.config.js
module.exports = {
  i18n: {
    defaultLocale: 'fr',
    locales: ['fr', 'en'],
    localeConfigs: {
      fr: { label: 'Français' },
      en: { label: 'English' }
    }
  }
};
```

#### Semaine 19-20: Tutoriels Vidéo

**Contenu (YouTube):**

1. **Installation en 5 minutes** (FR + EN)
   - Docker Compose setup
   - Premier login
   - Upload dictionnaire

2. **Configuration Breakout PostgreSQL** (FR + EN)
   - Créer base PostgreSQL
   - Configurer schéma
   - Lancer premier breakout

3. **Scheduler: Automatiser les Breakouts** (FR + EN)
   - Créer job
   - Configurer cron
   - Monitoring exécutions

4. **Monitoring et Logs** (FR + EN)
   - Interface logs temps réel
   - Filtres et recherche
   - Export et alertes

**Format:**
- Durée: 5-10 minutes
- Captions FR + EN
- Repo GitHub lié
- Playlist organisée

### Phase 4: Communauté (Mois 6) 🌍

#### Semaine 21-22: GitHub Pages Blog

**Structure:**
```
blog/
├── 2026-03-15-annonce-csweb-community.md
├── 2026-03-22-breakout-par-dictionnaire.md
├── 2026-04-05-multi-database-support.md
└── 2026-04-12-scheduler-web-ui.md
```

**Exemple post:**
```markdown
---
title: "CSWeb Community: Démocratiser CSPro pour l'Afrique"
date: 2026-03-15
author: Bouna DRAME
tags: [csweb, cspro, docker, open-source]
---

## Le Problème

Les instituts statistiques africains utilisent CSPro/CSWeb depuis des années,
mais le setup reste complexe...

## Notre Solution

CSWeb Community simplifie tout ça avec:
- 🐳 Docker: Déploiement en 1 commande
- 🎯 Breakout sélectif: Par dictionnaire
- 🖥️ UI moderne: Configuration sans SSH
- 📚 Docs complètes: FR + EN + Vidéos

## Démo

\`\`\`bash
git clone https://github.com/bounadrame/csweb-community
cd csweb-community
docker-compose up -d
\`\`\`

[Lire la suite →](/docs/getting-started)
```

#### Semaine 23-24: Discord + Forum

**Channels Discord:**
```
📢 annonces
💬 general
🆘 support
🐛 bugs
💡 features-requests
🌍 francophone
🌐 english
👨‍💻 dev-contributors
📊 show-your-work
```

**Modération:**
- Code of Conduct clair
- Modérateurs FR + EN
- Bot auto-réponses FAQ
- Intégration GitHub (issues, PRs)

#### Documentation Contribution

**CONTRIBUTING.md:**
```markdown
# Comment Contribuer

## 🐛 Signaler un Bug

1. Vérifier qu'il n'existe pas déjà
2. Créer une issue avec template
3. Fournir logs + config

## 💡 Proposer une Feature

1. Discuter sur Discord #features-requests
2. Créer une issue avec use case
3. Attendre validation avant PR

## 🔧 Soumettre une PR

1. Fork le repo
2. Branch: `feature/nom-feature`
3. Tests passants: `npm test && composer test`
4. PR vers `develop` (pas `master`)

## 📚 Améliorer la Doc

1. Fork `docs/`
2. Éditer Markdown
3. Preview: `npm run docs:dev`
4. PR avec captures d'écran
```

---

## 7. Documentation et Communauté

### 7.1 Site Principal (GitHub Pages)

**URL:** https://bounadrame.github.io/csweb-community/

**Structure:**
```
┌────────────────────────────────────────────────────────────┐
│  🏠 CSWeb Community Platform                               │
├────────────────────────────────────────────────────────────┤
│  [Accueil] [Docs] [Blog] [API] [Communauté] [GitHub]      │
│                                                            │
│  ┌────────────────────────────────────────────────────┐   │
│  │  Simplifiez CSWeb avec Docker + UI Moderne         │   │
│  │                                                     │   │
│  │  [🚀 Démarrer en 5 min]  [📚 Documentation]        │   │
│  │  [🎥 Tutoriels Vidéo]     [💬 Rejoindre Discord]    │   │
│  └────────────────────────────────────────────────────┘   │
│                                                            │
│  ✨ Fonctionnalités                                        │
│  ┌──────────┬──────────┬──────────┬──────────┐           │
│  │ Breakout │ Multi-DB │ Scheduler│ Monitoring│           │
│  │ Sélectif │ Support  │ Web UI   │ Temps Réel│           │
│  └──────────┴──────────┴──────────┴──────────┘           │
│                                                            │
│  📰 Derniers Articles                                      │
│  • Configuration PostgreSQL pour CSWeb (5 mars)            │
│  • Automatiser les Breakouts avec Scheduler (12 mars)     │
│  • Multi-Database: PostgreSQL vs MySQL (19 mars)          │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

**Inspiré par votre portfolio :** https://bounadrame.github.io/portfolio/

### 7.2 Documentation Technique

**Style:** Même qualité que docs Kairos API (210 pages)

**Documents clés:**

1. **INSTALLATION-GUIDE.md** (20 pages)
   - Prérequis système
   - Docker Compose setup
   - Configuration .env
   - Premier démarrage
   - Troubleshooting

2. **BREAKOUT-GUIDE.md** (30 pages)
   - Breakout sélectif vs global
   - Configuration multi-SGBD
   - Scheduler jobs
   - Monitoring exécutions
   - Optimisations performance

3. **API-REFERENCE.md** (40 pages)
   - Authentification JWT
   - Endpoints complets
   - Exemples curl/JavaScript
   - Codes erreur
   - Rate limiting

4. **MULTI-DATABASE-GUIDE.md** (25 pages)
   - PostgreSQL setup
   - MySQL setup
   - SQL Server setup
   - Comparatif performances
   - Migrations

5. **SCHEDULER-GUIDE.md** (20 pages)
   - Créer jobs via UI
   - Expressions cron
   - Retry strategies
   - Notifications
   - Historique

6. **MONITORING-GUIDE.md** (15 pages)
   - Dashboard overview
   - Logs streaming
   - Métriques Prometheus
   - Alertes
   - Export données

7. **CONTRIBUTING.md** (10 pages)
   - Code of Conduct
   - Setup dev environment
   - Git workflow
   - Tests
   - Documentation

**Total estimé:** ~160 pages (similaire à Kairos)

### 7.3 Tutoriels Vidéo (YouTube)

**Playlist:** "CSWeb Community - Guide Complet"

**Série 1: Démarrage** (FR + EN)
1. Introduction CSWeb Community (5 min)
2. Installation Docker (10 min)
3. Premier Breakout (8 min)

**Série 2: Configuration** (FR + EN)
4. Multi-Database Setup: PostgreSQL (12 min)
5. Multi-Database Setup: MySQL (10 min)
6. Configurer Scheduler Jobs (15 min)

**Série 3: Administration** (FR + EN)
7. Monitoring et Logs (12 min)
8. Gestion Utilisateurs et Permissions (10 min)
9. Backup et Restore (15 min)

**Série 4: Avancé** (FR + EN)
10. API Integration (20 min)
11. Déploiement Production (25 min)
12. Performance Tuning (18 min)

**Format:**
- Captions FR + EN
- Code disponible sur GitHub
- Timestamps dans description
- Liens docs pertinents

---

## 8. Roadmap

### v1.0 - Foundation (T2 2026) ✅

**Cible:** Juin 2026

**Fonctionnalités:**
- ✅ Docker Compose production-ready
- ✅ Breakout sélectif par dictionnaire
- ✅ Support PostgreSQL + MySQL
- ✅ API REST complète
- ✅ Scheduler web UI
- ✅ Logs monitoring temps réel
- ✅ Admin Panel React
- ✅ Documentation FR/EN (160 pages)
- ✅ Site GitHub Pages + Blog

**Métriques de succès:**
- 100 stars GitHub
- 10 contributeurs
- 5 instituts utilisateurs (beta)
- 1000 vues YouTube

### v1.5 - Enhancement (T3 2026) 🚀

**Cible:** Septembre 2026

**Fonctionnalités:**
- ✅ Support SQL Server
- ✅ Dashboard Grafana intégré
- ✅ Notifications (Email + Slack + Teams)
- ✅ Backup/Restore automatique
- ✅ Multi-tenancy (plusieurs organisations)
- ✅ RBAC avancé (rôles custom)
- ✅ API GraphQL
- ✅ Mobile app (React Native)

**Métriques de succès:**
- 500 stars GitHub
- 25 contributeurs
- 20 instituts utilisateurs
- 5000 vues YouTube
- 200 membres Discord

### v2.0 - Scale (T4 2026) 🌍

**Cible:** Décembre 2026

**Fonctionnalités:**
- ✅ High Availability (multi-servers)
- ✅ Load balancing automatique
- ✅ Réplication base de données
- ✅ CDN pour assets
- ✅ Kubernetes support
- ✅ Plugins marketplace
- ✅ Templates dictionnaires
- ✅ AI assistant (chat support)

**Métriques de succès:**
- 1000 stars GitHub
- 50 contributeurs
- 50 instituts utilisateurs
- 10000 vues YouTube
- 500 membres Discord
- Certification officielle

### v2.5 - SaaS (T1 2027) 💼

**Cible:** Mars 2027

**Fonctionnalités:**
- ✅ Offre SaaS hébergée
- ✅ Plans (Free, Pro, Enterprise)
- ✅ Billing intégré (Stripe)
- ✅ White-label
- ✅ Support 24/7
- ✅ SLA garantis
- ✅ Formation certifiante
- ✅ Consulting services

**Business Model:**
```
Free Tier (Communauté)
  - 1 dictionnaire
  - 10 breakouts/jour
  - Support communauté
  - Self-hosted uniquement

Pro ($49/mois)
  - 10 dictionnaires
  - Breakouts illimités
  - Support email
  - SaaS ou self-hosted
  - Backup automatique

Enterprise ($299/mois)
  - Dictionnaires illimités
  - Support 24/7
  - SLA 99.9%
  - Déploiement on-premise
  - Formation incluse
  - Custom features
```

---

## 9. Exemples de Configuration

### 9.1 Docker Compose Complet

```yaml
# docker-compose.yml
version: '3.8'

services:
  # CSWeb Core (Symfony + PHP-FPM)
  csweb:
    build:
      context: .
      dockerfile: docker/csweb/Dockerfile
    container_name: csweb_app
    volumes:
      - ./src:/var/www/csweb/src
      - ./app:/var/www/csweb/app
      - ./web:/var/www/csweb/web
      - ./var/logs:/var/www/csweb/var/logs
    environment:
      - APP_ENV=prod
      - DATABASE_URL=mysql://csweb:csweb_pwd@mysql:3306/csweb_metadata
      - JWT_SECRET=${JWT_SECRET}
    depends_on:
      - mysql
      - postgres
    networks:
      - csweb_network

  # Nginx (Reverse Proxy)
  nginx:
    image: nginx:1.24-alpine
    container_name: csweb_nginx
    ports:
      - "8080:80"
      - "443:443"
    volumes:
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf
      - ./docker/nginx/certs:/etc/nginx/certs
      - ./web:/var/www/csweb/web
    depends_on:
      - csweb
    networks:
      - csweb_network

  # MySQL (Métadonnées CSWeb)
  mysql:
    image: mysql:8.0
    container_name: csweb_mysql
    environment:
      - MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
      - MYSQL_DATABASE=csweb_metadata
      - MYSQL_USER=csweb
      - MYSQL_PASSWORD=${MYSQL_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/init.sql:/docker-entrypoint-initdb.d/init.sql
    ports:
      - "3306:3306"
    networks:
      - csweb_network

  # PostgreSQL (Breakout Analytics)
  postgres:
    image: postgres:14-alpine
    container_name: csweb_postgres
    environment:
      - POSTGRES_USER=kairos_analytics
      - POSTGRES_PASSWORD=${POSTGRES_PASSWORD}
      - POSTGRES_DB=kairos_analytics
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./docker/postgres/init.sql:/docker-entrypoint-initdb.d/init.sql
    ports:
      - "5432:5432"
    networks:
      - csweb_network

  # Admin Panel (React)
  admin:
    build:
      context: ./admin-panel
      dockerfile: Dockerfile
    container_name: csweb_admin
    ports:
      - "3000:3000"
    environment:
      - REACT_APP_API_URL=http://localhost:8080/api
    depends_on:
      - nginx
    networks:
      - csweb_network

  # Scheduler (Symfony Console + Supervisor)
  scheduler:
    build:
      context: .
      dockerfile: docker/scheduler/Dockerfile
    container_name: csweb_scheduler
    volumes:
      - ./src:/var/www/csweb/src
      - ./var/logs:/var/www/csweb/var/logs
    environment:
      - APP_ENV=prod
      - DATABASE_URL=mysql://csweb:csweb_pwd@mysql:3306/csweb_metadata
    depends_on:
      - mysql
      - postgres
    networks:
      - csweb_network

  # Prometheus (Métriques)
  prometheus:
    image: prom/prometheus:latest
    container_name: csweb_prometheus
    volumes:
      - ./docker/prometheus/prometheus.yml:/etc/prometheus/prometheus.yml
      - prometheus_data:/prometheus
    ports:
      - "9090:9090"
    networks:
      - csweb_network

  # Grafana (Dashboards)
  grafana:
    image: grafana/grafana:latest
    container_name: csweb_grafana
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=${GRAFANA_ADMIN_PASSWORD}
      - GF_INSTALL_PLUGINS=grafana-clock-panel
    volumes:
      - grafana_data:/var/lib/grafana
      - ./docker/grafana/dashboards:/etc/grafana/provisioning/dashboards
    ports:
      - "3001:3000"
    depends_on:
      - prometheus
    networks:
      - csweb_network

volumes:
  mysql_data:
  postgres_data:
  prometheus_data:
  grafana_data:

networks:
  csweb_network:
    driver: bridge
```

### 9.2 Variables d'Environnement (.env)

```bash
# .env.example
# Copy to .env and fill with your values

# ========================================
# GENERAL
# ========================================
APP_ENV=prod
APP_SECRET=your_app_secret_here_generate_with_openssl

# ========================================
# SECURITY
# ========================================
JWT_SECRET=your_jwt_secret_here_generate_with_openssl_rand_base64_32

# ========================================
# MYSQL (CSWeb Metadata)
# ========================================
MYSQL_ROOT_PASSWORD=secure_root_password_here
MYSQL_PASSWORD=csweb_secure_password_here
MYSQL_DATABASE=csweb_metadata
MYSQL_USER=csweb
MYSQL_HOST=mysql
MYSQL_PORT=3306

# ========================================
# POSTGRESQL (Breakout Analytics)
# ========================================
POSTGRES_PASSWORD=kairos_analytics_secure_password_here
POSTGRES_DB=kairos_analytics
POSTGRES_USER=kairos_analytics
POSTGRES_HOST=postgres
POSTGRES_PORT=5432

# ========================================
# SQL SERVER (Optional - Enterprise)
# ========================================
SQLSERVER_HOST=sqlserver
SQLSERVER_PORT=1433
SQLSERVER_DATABASE=kairos_analytics
SQLSERVER_USER=sa
SQLSERVER_PASSWORD=YourStrong!Passw0rd

# ========================================
# BREAKOUT CONFIGURATION
# ========================================
# Default SGBD for new dictionaries (postgresql|mysql|sqlserver)
DEFAULT_BREAKOUT_DB_TYPE=postgresql

# Default cron expression for breakout jobs
DEFAULT_BREAKOUT_CRON=0 0 1 * * *

# Auto-seed breakout jobs on startup
BREAKOUT_AUTO_SEED=true

# Max breakout duration (seconds)
BREAKOUT_MAX_DURATION=600

# ========================================
# SCHEDULER
# ========================================
# Enable/disable scheduler service
SCHEDULER_ENABLED=true

# Scheduler check interval (seconds)
SCHEDULER_CHECK_INTERVAL=60

# Max concurrent jobs
SCHEDULER_MAX_CONCURRENT_JOBS=5

# ========================================
# LOGS & MONITORING
# ========================================
# Log level (DEBUG|INFO|WARNING|ERROR|CRITICAL)
LOG_LEVEL=INFO

# Log rotation (days)
LOG_ROTATION_DAYS=7

# Log max size (MB)
LOG_MAX_SIZE_MB=500

# Enable logs streaming (SSE)
LOGS_STREAMING_ENABLED=true

# ========================================
# API
# ========================================
# API rate limiting (requests per minute)
API_RATE_LIMIT=60

# API timeout (seconds)
API_TIMEOUT=30

# CORS allowed origins (comma-separated)
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080

# ========================================
# NOTIFICATIONS
# ========================================
# Email notifications
MAILER_DSN=smtp://user:pass@smtp.example.com:587

# Slack webhook URL
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Microsoft Teams webhook URL
TEAMS_WEBHOOK_URL=https://outlook.office.com/webhook/YOUR/WEBHOOK/URL

# ========================================
# MONITORING (Prometheus/Grafana)
# ========================================
PROMETHEUS_ENABLED=false
GRAFANA_ADMIN_PASSWORD=admin_secure_password_here

# ========================================
# BACKUP
# ========================================
# Auto backup enabled
BACKUP_ENABLED=true

# Backup cron expression
BACKUP_CRON=0 0 2 * * *

# Backup retention (days)
BACKUP_RETENTION_DAYS=30

# Backup destination (local|s3|gcs)
BACKUP_DESTINATION=local
BACKUP_LOCAL_PATH=/var/backups/csweb

# S3 Configuration (if BACKUP_DESTINATION=s3)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_S3_BUCKET=
AWS_S3_REGION=us-east-1

# ========================================
# DEVELOPMENT
# ========================================
# Enable debug mode (dev only)
DEBUG=false

# Enable Symfony profiler (dev only)
SYMFONY_PROFILER=false

# ========================================
# GENERATE SECURE SECRETS
# ========================================
# Run these commands to generate secure values:
#
# APP_SECRET:
#   openssl rand -hex 32
#
# JWT_SECRET:
#   openssl rand -base64 32
#
# MYSQL_PASSWORD / POSTGRES_PASSWORD:
#   openssl rand -base64 24
```

### 9.3 Configuration Scheduler

```php
// src/AppBundle/Service/SchedulerManager.php
namespace AppBundle\Service;

use Symfony\Component\Lock\LockFactory;
use Doctrine\DBAL\Connection;

class SchedulerManager
{
    private Connection $connection;
    private LockFactory $lockFactory;
    private array $jobs = [];

    public function __construct(Connection $connection, LockFactory $lockFactory)
    {
        $this->connection = $connection;
        $this->lockFactory = $lockFactory;
    }

    /**
     * Register a breakout job
     */
    public function registerBreakoutJob(string $jobId, string $dictionary, string $cron): void
    {
        $this->jobs[$jobId] = [
            'type' => 'breakout',
            'dictionary' => $dictionary,
            'cron' => $cron,
            'enabled' => false,
            'runnable' => function() use ($dictionary) {
                $this->executeBreakout($dictionary);
            }
        ];

        // Persist to database
        $this->connection->executeStatement(
            'INSERT INTO scheduler_jobs (job_id, job_type, cron_expression, enabled, params_json, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE cron_expression = VALUES(cron_expression)',
            [
                $jobId,
                'breakout',
                $cron,
                false,
                json_encode(['dictionary' => $dictionary])
            ]
        );
    }

    /**
     * Run scheduler loop
     */
    public function run(): void
    {
        while (true) {
            $dueJobs = $this->getDueJobs();

            foreach ($dueJobs as $job) {
                // Acquire lock to prevent concurrent execution
                $lock = $this->lockFactory->createLock('scheduler_job_' . $job['job_id'], 300);

                if ($lock->acquire()) {
                    try {
                        $this->executeJob($job);
                        $this->recordSuccess($job);
                    } catch (\Exception $e) {
                        $this->recordFailure($job, $e);
                    } finally {
                        $lock->release();
                    }
                }
            }

            sleep(60); // Check every minute
        }
    }

    private function getDueJobs(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM scheduler_jobs
             WHERE enabled = 1
             AND (next_run_at IS NULL OR next_run_at <= NOW())'
        );
    }

    private function executeJob(array $job): void
    {
        $startTime = microtime(true);

        // Update status to RUNNING
        $this->connection->executeStatement(
            'UPDATE scheduler_jobs SET last_run_at = NOW(), last_run_status = ? WHERE job_id = ?',
            ['RUNNING', $job['job_id']]
        );

        // Execute job based on type
        $params = json_decode($job['params_json'], true);

        if ($job['job_type'] === 'breakout') {
            $this->executeBreakout($params['dictionary']);
        }

        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Calculate next run
        $nextRun = $this->calculateNextRun($job['cron_expression']);

        $this->connection->executeStatement(
            'UPDATE scheduler_jobs
             SET last_run_status = ?,
                 last_run_duration_ms = ?,
                 next_run_at = ?,
                 last_error_message = NULL
             WHERE job_id = ?',
            ['SUCCESS', $durationMs, $nextRun, $job['job_id']]
        );
    }

    private function executeBreakout(string $dictionary): void
    {
        // Call existing breakout logic
        $command = sprintf(
            'php %s/bin/console csweb:process-cases-by-dict %s',
            __DIR__ . '/../../..',
            escapeshellarg($dictionary)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Breakout failed: ' . implode("\n", $output));
        }
    }

    private function calculateNextRun(string $cronExpression): string
    {
        // Use Cron library to calculate next run
        $cron = new \Cron\CronExpression($cronExpression);
        return $cron->getNextRunDate()->format('Y-m-d H:i:s');
    }

    private function recordFailure(array $job, \Exception $e): void
    {
        $this->connection->executeStatement(
            'UPDATE scheduler_jobs
             SET last_run_status = ?,
                 last_error_message = ?
             WHERE job_id = ?',
            ['FAILED', $e->getMessage(), $job['job_id']]
        );
    }
}
```

### 9.4 Exemple API Client (JavaScript)

```typescript
// admin-panel/src/api/client.ts
import axios from 'axios';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8080/api';

// Create axios instance with defaults
const apiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json'
  }
});

// Add JWT token to requests
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('jwt_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Refresh token on 401
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // Clear token and redirect to login
      localStorage.removeItem('jwt_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

// API Methods
export const api = {
  // Authentication
  auth: {
    login: async (username: string, password: string) => {
      const { data } = await apiClient.post('/auth/login', { username, password });
      localStorage.setItem('jwt_token', data.accessToken);
      return data;
    },
    logout: () => {
      localStorage.removeItem('jwt_token');
    }
  },

  // Dictionaries
  dictionaries: {
    list: async () => {
      const { data } = await apiClient.get('/dictionaries');
      return data;
    },
    get: async (id: number) => {
      const { data } = await apiClient.get(`/dictionaries/${id}`);
      return data;
    },
    upload: async (file: File) => {
      const formData = new FormData();
      formData.append('dictionary', file);
      const { data } = await apiClient.post('/dictionaries/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      return data;
    }
  },

  // Breakout
  breakout: {
    // List all breakout jobs
    listJobs: async () => {
      const { data } = await apiClient.get('/breakout/jobs');
      return data;
    },

    // Get job details
    getJob: async (jobId: string) => {
      const { data } = await apiClient.get(`/breakout/jobs/${jobId}`);
      return data;
    },

    // Create or update job
    saveJob: async (job: {
      jobId: string;
      dictionaryId: number;
      cronExpression: string;
      enabled: boolean;
      targetDatabase: {
        type: 'postgresql' | 'mysql' | 'sqlserver';
        host: string;
        port?: number;
        schema: string;
        user: string;
        password: string;
      };
    }) => {
      const { data } = await apiClient.post('/breakout/jobs', job);
      return data;
    },

    // Update job (partial)
    updateJob: async (jobId: string, updates: Partial<any>) => {
      const { data } = await apiClient.patch(`/breakout/jobs/${jobId}`, updates);
      return data;
    },

    // Trigger breakout manually
    trigger: async (dictionary: string) => {
      const { data } = await apiClient.post(`/breakout/${dictionary}/trigger`);
      return data;
    },

    // Start/stop job
    start: async (jobId: string) => {
      const { data } = await apiClient.post(`/breakout/jobs/${jobId}/start`);
      return data;
    },
    stop: async (jobId: string) => {
      const { data } = await apiClient.post(`/breakout/jobs/${jobId}/stop`);
      return data;
    }
  },

  // Logs
  logs: {
    // List log files
    listFiles: async () => {
      const { data } = await apiClient.get('/logs/files');
      return data;
    },

    // Fetch logs with filters
    fetch: async (params: {
      file?: string;
      lines?: number;
      level?: 'DEBUG' | 'INFO' | 'WARNING' | 'ERROR' | 'CRITICAL';
      search?: string;
      raw?: boolean;
    }) => {
      const { data } = await apiClient.get('/logs', { params });
      return data;
    },

    // Stream logs (SSE)
    stream: (params: {
      level?: string;
      dictionary?: string;
    }, onMessage: (log: any) => void) => {
      const query = new URLSearchParams(params).toString();
      const eventSource = new EventSource(
        `${API_BASE_URL}/logs/stream?${query}`,
        { withCredentials: true }
      );

      eventSource.onmessage = (event) => {
        onMessage(JSON.parse(event.data));
      };

      return eventSource;
    }
  },

  // Schemas
  schemas: {
    // List all dictionaries with schema config status
    list: async () => {
      const { data } = await apiClient.get('/schemas');
      return data;
    },

    // Get schema config for a dictionary
    get: async (dictionaryId: number) => {
      const { data } = await apiClient.get(`/schemas/${dictionaryId}`);
      return data;
    },

    // Register/update schema
    register: async (config: {
      dictionaryId: number;
      dbType: 'postgresql' | 'mysql' | 'sqlserver';
      hostName: string;
      port?: number;
      schemaName: string;
      schemaUserName: string;
      schemaPassword: string;
      useSsl?: boolean;
    }) => {
      const { data } = await apiClient.post('/schemas', config);
      return data;
    },

    // Unregister schema
    unregister: async (dictionaryId: number) => {
      const { data } = await apiClient.delete(`/schemas/${dictionaryId}`);
      return data;
    },

    // Test connection
    testConnection: async (config: {
      dbType: string;
      hostName: string;
      port?: number;
      schemaName: string;
      schemaUserName: string;
      schemaPassword: string;
    }) => {
      const { data } = await apiClient.post('/schemas/test-connection', config);
      return data;
    }
  },

  // Dashboard stats
  stats: {
    overview: async () => {
      const { data } = await apiClient.get('/stats/overview');
      return data;
    },
    breakouts: async (params: { days?: number }) => {
      const { data } = await apiClient.get('/stats/breakouts', { params });
      return data;
    }
  }
};

export default apiClient;
```

---

## 10. Conclusion et Prochaines Étapes

### 10.1 Pourquoi ce Projet est Important

✅ **Gap réel** dans l'écosystème CSPro/CSWeb
✅ **Impact Afrique** : Instituts statistiques nationaux
✅ **Timing parfait** : Expertise Kairos API + CSWeb 8 PG ready
✅ **Communauté manquante** : Besoin d'une plateforme francophone
✅ **Open-source viable** : Modèle SaaS optionnel long terme

### 10.2 Recommandations Stratégiques

#### Priorité 1: Validation Rapide (Mois 1)

1. **MVP Docker** en 2 semaines
   - Docker Compose fonctionnel
   - CSWeb 8 PG (existant)
   - Breakout sélectif (déjà fait par Assietou)
   - README complet

2. **Beta testers** (Semaine 3-4)
   - ANSD (Assietou Diagne)
   - 2-3 autres INS africains
   - Feedback itératif

#### Priorité 2: Communauté (Mois 2-3)

1. **GitHub Pages** style portfolio
2. **Blog** (1 article/semaine)
3. **Discord** communauté
4. **Première vidéo** YouTube

#### Priorité 3: Fonctionnalités (Mois 4-6)

1. **Admin Panel** React
2. **Scheduler** Web UI
3. **Logs** monitoring
4. **Multi-DB** (PostgreSQL prioritaire)

### 10.3 Stack Recommandée

**Backend:**
- ✅ Symfony 5.4 (existant, stable)
- ✅ PHP 8.0+ (performance, typage)
- ✅ PostgreSQL 14+ (analytics)
- ✅ MySQL 8.0 (metadata)

**Frontend:**
- ✅ React 18 + TypeScript (écosystème)
- ✅ Tailwind CSS (rapidité)
- ✅ Vite (build moderne)

**DevOps:**
- ✅ Docker + Compose (portabilité)
- ✅ GitHub Actions (CI/CD gratuit)
- ✅ GitHub Pages (docs/blog gratuit)

### 10.4 Business Model Recommandé

#### Phase 1-2 (2026): 100% Open-Source Gratuit

- Focus communauté
- Beta testers INS
- Contributions externes
- Sponsoring GitHub (optionnel)

#### Phase 3 (2027): Freemium + Services

**Free (self-hosted):**
- Tout le code open-source
- Documentation complète
- Support communauté

**Services payants:**
- Formation certifiante ($500/personne)
- Consulting setup ($2000/institut)
- Support premium ($200/mois)
- SaaS hébergé ($49-299/mois)

### 10.5 Prochaines Actions Immédiates

#### Cette Semaine

1. [ ] Créer repo GitHub `csweb-community`
2. [ ] Copier code CSWeb 8 PG (avec mods Assietou)
3. [ ] Setup Docker Compose basique
4. [ ] README initial avec vision

#### Semaine Prochaine

1. [ ] Docker multi-services (MySQL + PostgreSQL)
2. [ ] Script init automatique
3. [ ] Test complet end-to-end
4. [ ] Documentation INSTALLATION.md

#### Mois 1

1. [ ] MVP Docker production-ready
2. [ ] Blog GitHub Pages
3. [ ] 3 beta testers (ANSD + 2)
4. [ ] Première vidéo YouTube

---

## Annexes

### A. Comparatif SGBD pour Breakout

| Critère | PostgreSQL ⭐ | MySQL | SQL Server |
|---------|--------------|-------|------------|
| **Performance analytics** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| **JSON support** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Window functions** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Géospatial (PostGIS)** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐ |
| **Coût licence** | Gratuit | Gratuit | Payant |
| **Écosystème BI** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Facilité setup** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Docker support** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |

**Recommandation:** PostgreSQL pour analytics, MySQL pour legacy/simple.

### B. Ressources Utiles

**Documentation CSPro:**
- https://www.census.gov/data/software/cspro.html
- https://www.csprousers.org/help/CSWeb/

**Communauté:**
- https://www.csprousers.org/forum/
- https://github.com/csprousers

**Inspiration:**
- Docker CSWeb: https://github.com/csprousers/docker-csweb
- Kairos API: `/Users/bdrame/Developer/personal/statinfo/ia-analyser/sentiment-analyzer-api`
- Portfolio: https://bounadrame.github.io/portfolio/

---

**Auteur:** Bouna DRAME
**Contributrice:** Assietou Diagne (ANSD)
**Date:** 14 Mars 2026
**Version:** 1.0

**Contact:**
- GitHub: https://github.com/bounadrame
- Email: bdrame@statinfo.sn
- LinkedIn: linkedin.com/in/boubacar-ndoye-drame
