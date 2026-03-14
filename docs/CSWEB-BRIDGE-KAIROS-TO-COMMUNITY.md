# Pont entre Kairos API et CSWeb Community Platform

> Comment l'expérience Kairos API alimente le projet CSWeb Community

**Date:** 14 Mars 2026

---

## 📋 Vue d'Ensemble

Ce document explique comment **réutiliser l'expérience et le code** de Kairos API (notamment les webhooks CSWeb) dans le nouveau projet **CSWeb Community Platform**.

---

## 1. Documentations Existantes Kairos API

### 1.1 Webhooks CSWeb (docs/api-integration/)

Nous avons créé **4 documents complets** sur les webhooks CSWeb :

1. **`CSWEB-WEBHOOKS-GUIDE.md`** (36 KB, 60 pages)
   - Architecture complète (Frontend → Kairos → CSWeb)
   - 3 webhooks PHP détaillés
   - Déploiement, sécurité, troubleshooting
   - **Réutilisable:** Sections 2, 3, 5, 7

2. **`CSWEB-QUICK-REFERENCE.md`** (11 KB, 10 pages)
   - Commandes curl essentielles
   - Workflow typique avec variables
   - **Réutilisable:** 100% pour CSWeb Community docs

3. **`csweb-webhook/README.md`** (7.5 KB)
   - Documentation des 3 scripts PHP
   - Installation serveur CSWeb
   - **Réutilisable:** Base pour module "Remote Management"

4. **`INDEX.md`** (12 KB)
   - Navigation entre docs
   - Parcours de lecture
   - **Réutilisable:** Template pour docs CSWeb Community

### 1.2 Commande Breakout par Dictionnaire

**Commande Symfony existante :**
```bash
php bin/console csweb:process-cases-by-dict <DICT>
```

**Exemple :**
```bash
php bin/console csweb:process-cases-by-dict EVAL_PRODUCTEURS_USAID
```

Cette commande est **déjà implémentée** dans le projet CSWeb 8 PG grâce aux modifications d'Assietou Diagne (voir `DOC-20251121-WA0004.pdf`).

**Fichiers clés modifiés :**
- `src/AppBundle/CSPro/DictionarySchemaHelper.php`
- `src/AppBundle/Command/ProcessCasesByDictCommand.php` (probable)

---

## 2. Réutilisation dans CSWeb Community

### 2.1 Architecture Commune

```
┌───────────────────────────────────────────────────────────────┐
│                  Kairos API (Existant)                        │
│  ┌─────────────┐   ┌─────────────┐   ┌─────────────┐         │
│  │  Frontend   │──▶│  Kairos API │──▶│  Webhooks   │──▶CSWeb│
│  │  (Angular)  │   │(Spring Boot)│   │   (PHP)     │   Core │
│  └─────────────┘   └─────────────┘   └─────────────┘         │
└───────────────────────────────────────────────────────────────┘
                            │
                            │ Expérience réutilisée
                            ↓
┌───────────────────────────────────────────────────────────────┐
│              CSWeb Community Platform (Nouveau)               │
│  ┌─────────────┐   ┌─────────────┐   ┌─────────────┐         │
│  │ Admin Panel │──▶│  API REST   │──▶│  CSWeb Core │         │
│  │   (React)   │   │  (Symfony)  │   │  + Webhooks │         │
│  └─────────────┘   └─────────────┘   └─────────────┘         │
│         │                  │                  │               │
│         └──────────────────┴──────────────────┘               │
│                    Tout intégré                               │
└───────────────────────────────────────────────────────────────┘
```

### 2.2 Code Réutilisable

#### A. Webhooks PHP (100% réutilisables)

**Source:** `docs/api-integration/csweb-webhook/`

**Fichiers à intégrer dans CSWeb Community :**

1. **`breakout-webhook.php`**
   ```php
   // Déjà implémenté dans Kairos
   // À intégrer directement dans CSWeb Community
   // Location: /var/www/csweb/api/breakout-webhook.php
   ```

2. **`log-reader-webhook.php`**
   ```php
   // Lecture logs Symfony temps réel
   // À intégrer: /var/www/csweb/api/log-reader-webhook.php
   ```

3. **`dictionary-schema-webhook.php`**
   ```php
   // Gestion schémas MySQL/PostgreSQL
   // À intégrer: /var/www/csweb/api/dictionary-schema-webhook.php
   ```

**Intégration dans CSWeb Community:**

```yaml
# docker-compose.yml (CSWeb Community)
services:
  csweb:
    volumes:
      - ./src/webhooks/breakout-webhook.php:/var/www/csweb/api/breakout-webhook.php
      - ./src/webhooks/log-reader-webhook.php:/var/www/csweb/api/log-reader-webhook.php
      - ./src/webhooks/dictionary-schema-webhook.php:/var/www/csweb/api/dictionary-schema-webhook.php
```

#### B. API REST Endpoints (Kairos → CSWeb Community)

| Endpoint Kairos | Endpoint CSWeb Community | Réutilisation |
|----------------|-------------------------|---------------|
| `GET /api/admin/cspro/breakout/dictionaries` | `GET /api/dictionaries` | ✅ 90% |
| `POST /api/admin/cspro/breakout/sync` | `POST /api/breakout/jobs/sync` | ✅ 100% |
| `POST /api/admin/cspro/breakout/{dict}/trigger` | `POST /api/breakout/{dict}/trigger` | ✅ 100% |
| `GET /api/admin/cspro/breakout/status` | `GET /api/breakout/jobs` | ✅ 80% |
| `GET /api/admin/cspro/logs` | `GET /api/logs` | ✅ 100% |
| `GET /api/admin/cspro/logs/stream` | `GET /api/logs/stream` | ✅ 100% |
| `GET /api/admin/cspro/schemas` | `GET /api/schemas` | ✅ 100% |

**Code à porter (exemple) :**

```php
// Kairos: src/main/java/com/project/sentiment/service/CsProBreakoutService.java
public BreakoutResult triggerBreakout(String dictionary) {
    validateDictionary(dictionary);
    return csProWebClient.triggerBreakout(dictionary);
}

// ↓ Port vers Symfony (CSWeb Community)

// CSWeb Community: src/AppBundle/Service/BreakoutService.php
public function triggerBreakout(string $dictionary): BreakoutResult {
    $this->validateDictionary($dictionary);
    return $this->webhookClient->triggerBreakout($dictionary);
}
```

#### C. Scheduler Service (Kairos → CSWeb Community)

**Kairos:** `DynamicSchedulerService.java`

**Pattern à porter:**
- Jobs stockés en base avec `job_id`, `cron_expression`, `enabled`
- Runnable enregistré par jobId
- Métriques: `lastRunAt`, `lastRunStatus`, `lastRunDurationMs`, `nextRunAt`

**Implementation CSWeb Community:**

```php
// src/AppBundle/Service/SchedulerService.php
namespace AppBundle\Service;

use Doctrine\DBAL\Connection;
use Cron\CronExpression;

class SchedulerService
{
    private Connection $connection;
    private array $jobs = [];

    public function registerBreakoutJob(string $dictionary, string $cron): void
    {
        $jobId = 'BREAKOUT_' . $dictionary;

        $this->jobs[$jobId] = [
            'runnable' => function() use ($dictionary) {
                $this->executeBreakout($dictionary);
            },
            'cron' => $cron
        ];

        // Persist to DB (pattern Kairos)
        $this->connection->executeStatement(
            'INSERT INTO scheduler_jobs (job_id, cron_expression, enabled, params_json, created_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE cron_expression = VALUES(cron_expression)',
            [
                $jobId,
                $cron,
                false,
                json_encode(['dictionary' => $dictionary])
            ]
        );
    }

    private function executeBreakout(string $dictionary): void
    {
        // Appel commande Symfony
        $command = sprintf(
            'php %s/bin/console csweb:process-cases-by-dict %s',
            $this->kernelProjectDir,
            escapeshellarg($dictionary)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Breakout failed: ' . implode("\n", $output));
        }
    }

    public function run(): void
    {
        while (true) {
            $dueJobs = $this->getDueJobs();

            foreach ($dueJobs as $job) {
                $this->executeJob($job);
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
}
```

#### D. Logs Parsing (Kairos → CSWeb Community)

**Kairos:** `CsProBreakoutService.parseLogEntries()`

**Pattern Symfony:**
```
[2026-03-14T16:50:01.266568+00:00] app.ERROR: Failed deleting tables...
```

**Regex (réutilisable 100%):**
```php
// Pattern Kairos (Java)
private static final Pattern LOG_LINE_PATTERN = Pattern.compile("^\\[(.+?)]\\s+(\\w+)\\.(\\w+):\\s+(.*)$");

// Port PHP (CSWeb Community)
const LOG_LINE_PATTERN = '/^\[(.+?)\]\s+(\w+)\.(\w+):\s+(.*)$/';

preg_match(LOG_LINE_PATTERN, $line, $matches);
// $matches[1] = timestamp
// $matches[2] = channel
// $matches[3] = level
// $matches[4] = message + context
```

**Code complet réutilisable:**

```php
// src/AppBundle/Service/LogParserService.php
namespace AppBundle\Service;

class LogParserService
{
    private const LOG_LINE_PATTERN = '/^\[(.+?)\]\s+(\w+)\.(\w+):\s+(.*)$/';

    public function parseLogEntries(string $content, ?string $level = null, ?string $search = null): array
    {
        if (empty($content)) {
            return [];
        }

        $allEntries = [];
        $current = null;

        foreach (explode("\n", $content) as $line) {
            if (preg_match(self::LOG_LINE_PATTERN, $line, $matches)) {
                // Nouvelle entrée
                if ($current !== null) {
                    $allEntries[] = $current;
                }

                $rawMessage = $matches[4];
                $message = $rawMessage;
                $context = null;

                // Séparer message du contexte JSON
                $jsonStart = strpos($rawMessage, ' {');
                if ($jsonStart !== false) {
                    $message = trim(substr($rawMessage, 0, $jsonStart));
                    $context = trim(substr($rawMessage, $jsonStart));
                }

                $current = [
                    'timestamp' => $matches[1],
                    'channel'   => $matches[2],
                    'level'     => $matches[3],
                    'message'   => $message,
                    'context'   => $context
                ];
            } elseif ($current !== null && !empty(trim($line))) {
                // Continuation (stacktrace)
                $current['context'] = ($current['context'] ?? '') . "\n" . $line;
            }
        }

        // Dernière entrée
        if ($current !== null) {
            $allEntries[] = $current;
        }

        // Filtres
        return array_filter($allEntries, function($entry) use ($level, $search) {
            if ($level && strcasecmp($entry['level'], $level) !== 0) {
                return false;
            }
            if ($search) {
                $searchLower = strtolower($search);
                $inMessage = stripos($entry['message'], $searchLower) !== false;
                $inContext = $entry['context'] && stripos($entry['context'], $searchLower) !== false;
                if (!$inMessage && !$inContext) {
                    return false;
                }
            }
            return true;
        });
    }
}
```

---

## 3. Documentation à Porter

### 3.1 Carte de Réutilisation

| Document Kairos | Section | Réutilisable CSWeb Community | Adaptation |
|----------------|---------|------------------------------|------------|
| **CSWEB-WEBHOOKS-GUIDE.md** | Section 2: Les 3 Webhooks | ✅ 100% | Aucune |
| | Section 3: Déploiement | ✅ 90% | Chemin Docker |
| | Section 5: Sécurité | ✅ 100% | Aucune |
| | Section 6: Monitoring | ✅ 95% | Noms services |
| | Section 7: Troubleshooting | ✅ 100% | Aucune |
| | Section 8: Exemples | ✅ 80% | URLs |
| **CSWEB-QUICK-REFERENCE.md** | Tout | ✅ 95% | URLs |
| **csweb-webhook/README.md** | Installation | ✅ 100% | Aucune |
| **INDEX.md** | Structure | ✅ 100% | Template |

### 3.2 Nouveaux Documents CSWeb Community

**À créer (inspirés Kairos):**

1. **`INSTALLATION-GUIDE.md`**
   - Base: `CSWEB-WEBHOOKS-GUIDE.md` Section 3
   - Ajout: Docker Compose complet
   - Ajout: Variables .env complètes

2. **`BREAKOUT-GUIDE.md`**
   - Base: `CSWEB-WEBHOOKS-GUIDE.md` Section 2.1 + 4.1
   - Ajout: Multi-SGBD (PostgreSQL, MySQL, SQL Server)
   - Ajout: UI Admin Panel

3. **`API-REFERENCE.md`**
   - Base: `CSWEB-WEBHOOKS-GUIDE.md` Section 4.2
   - Ajout: Endpoints Scheduler, Logs, Schemas
   - Ajout: OpenAPI auto-générée

4. **`SCHEDULER-GUIDE.md`**
   - Base: `CSWEB-WEBHOOKS-GUIDE.md` Section 4.4
   - Ajout: UI Web (React)
   - Ajout: Cron Expression Builder

5. **`MONITORING-GUIDE.md`**
   - Base: `CSWEB-WEBHOOKS-GUIDE.md` Section 6
   - Ajout: Dashboard Grafana
   - Ajout: SSE Streaming logs

---

## 4. Code à Migrer (Checklist)

### 4.1 Backend (Spring Boot → Symfony)

#### Services

- [ ] **CsProBreakoutService.java** → **BreakoutService.php**
  - `listDictionaries()`
  - `syncDictionaries()`
  - `triggerBreakout(dict)`
  - `listBreakoutJobStatus()`
  - `parseLogEntries(content, level, search)`

- [ ] **CsProWebClient.java** → **WebhookClient.php**
  - `triggerBreakout(dict)` → Appel webhook
  - `fetchLog(file, lines)` → Appel webhook
  - `listDictionarySchemas()` → Appel webhook
  - Bearer Token auth

- [ ] **DynamicSchedulerService.java** → **SchedulerService.php**
  - `registerJob(jobId, runnable)`
  - `createJob(config)`
  - `updateJob(jobId, updates)`
  - `listJobsByPrefix(prefix)`

#### Controllers

- [ ] **CsProBreakoutController.java** → **BreakoutController.php**
  - `GET /dictionaries`
  - `POST /breakout/sync`
  - `POST /breakout/{dict}/trigger`
  - `GET /breakout/status`

- [ ] **SchedulerJobController.java** → **SchedulerController.php**
  - `GET /scheduler/jobs`
  - `GET /scheduler/jobs/{id}`
  - `PATCH /scheduler/jobs/{id}`
  - `POST /scheduler/jobs/{id}/start|stop|trigger`

#### Entities / Models

- [ ] **SchedulerJobEntity.java** → **SchedulerJob.php** (Doctrine)
  ```sql
  CREATE TABLE scheduler_jobs (
    job_id VARCHAR(255) PRIMARY KEY,
    cron_expression VARCHAR(255) NOT NULL,
    enabled BOOLEAN DEFAULT FALSE,
    params_json TEXT,
    last_run_at DATETIME,
    last_run_status VARCHAR(50),
    last_run_duration_ms INT,
    next_run_at DATETIME,
    last_error_message TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
  );
  ```

### 4.2 Frontend (Angular → React)

#### Pages

- [ ] **Breakout Status Page** (Angular) → **BreakoutJobs.tsx** (React)
  - Liste jobs avec statut
  - Dernière exécution
  - Prochaine exécution
  - Actions (start/stop/trigger)

- [ ] **Logs Page** (Angular) → **LogsMonitor.tsx** (React)
  - SSE streaming temps réel
  - Filtres (niveau, dictionnaire, search)
  - Auto-scroll + pause
  - Export CSV/JSON

#### Components

- [ ] **Job Card** → **JobCard.tsx**
  ```tsx
  <JobCard
    job={job}
    onStart={() => api.breakout.start(job.jobId)}
    onStop={() => api.breakout.stop(job.jobId)}
    onTrigger={() => api.breakout.trigger(job.dictionary)}
  />
  ```

- [ ] **Log Viewer** → **LogViewer.tsx**
  ```tsx
  <LogViewer
    logs={logs}
    isLive={isConnected}
    filters={<LogFilters />}
    onExport={exportLogs}
  />
  ```

### 4.3 DevOps

#### Docker

- [ ] **Dockerfile** (Spring Boot) → **Dockerfile** (Symfony + PHP-FPM)
  ```dockerfile
  FROM php:8.0-fpm-alpine
  RUN apk add --no-cache postgresql-dev mysql-client
  RUN docker-php-ext-install pdo pdo_pgsql pdo_mysql
  WORKDIR /var/www/csweb
  COPY . .
  RUN composer install --no-dev --optimize-autoloader
  CMD ["php-fpm"]
  ```

- [ ] **docker-compose.yml** (Kairos) → **docker-compose.yml** (CSWeb Community)
  - Service `csweb` (Symfony)
  - Service `nginx` (Reverse proxy)
  - Service `mysql` (Metadata)
  - Service `postgres` (Analytics)
  - Service `admin` (React)
  - Service `scheduler` (Symfony Console)

#### CI/CD

- [ ] **GitHub Actions** (Kairos) → **GitHub Actions** (CSWeb Community)
  ```yaml
  name: CI
  on: [push, pull_request]
  jobs:
    test:
      runs-on: ubuntu-latest
      steps:
        - uses: actions/checkout@v3
        - name: Setup PHP
          uses: shivammathur/setup-php@v2
          with:
            php-version: 8.0
        - name: Install dependencies
          run: composer install
        - name: Run tests
          run: composer test
  ```

---

## 5. Variables d'Environnement

### 5.1 Kairos API (.env)

```bash
# Kairos API - Variables CSWeb
CSPRO_BASE_URL=http://193.203.15.16/kairos
CSPRO_WEBHOOK_URL=http://193.203.15.16/kairos/breakout-webhook.php
CSPRO_LOG_READER_URL=http://193.203.15.16/kairos/log-reader-webhook.php
CSPRO_DICTIONARY_SCHEMA_URL=http://193.203.15.16/kairos/dictionary-schema-webhook.php
CSPRO_WEBHOOK_TOKEN=kairos_breakout_2024
CSPRO_BREAKOUT_CRON=0 0 1 * * ?
CSPRO_BREAKOUT_AUTO_SEED=true
```

### 5.2 CSWeb Community (.env)

```bash
# CSWeb Community - Même pattern, noms simplifiés

# App
APP_ENV=prod
APP_SECRET=generate_with_openssl_rand_hex_32

# Security
JWT_SECRET=generate_with_openssl_rand_base64_32

# MySQL (Metadata)
MYSQL_HOST=mysql
MYSQL_PORT=3306
MYSQL_DATABASE=csweb_metadata
MYSQL_USER=csweb
MYSQL_PASSWORD=secure_password_here
MYSQL_ROOT_PASSWORD=secure_root_password_here

# PostgreSQL (Breakout Default)
POSTGRES_HOST=postgres
POSTGRES_PORT=5432
POSTGRES_DATABASE=csweb_analytics
POSTGRES_USER=csweb_analytics
POSTGRES_PASSWORD=secure_password_here

# Webhooks (Internal - mêmes que Kairos)
WEBHOOK_TOKEN=generate_with_openssl_rand_base64_32
WEBHOOK_TIMEOUT=300

# Breakout
BREAKOUT_DEFAULT_DB_TYPE=postgresql
BREAKOUT_DEFAULT_CRON=0 0 1 * * *
BREAKOUT_AUTO_SEED=true
BREAKOUT_MAX_DURATION=600

# Scheduler
SCHEDULER_ENABLED=true
SCHEDULER_CHECK_INTERVAL=60
SCHEDULER_MAX_CONCURRENT_JOBS=5

# Logs
LOG_LEVEL=INFO
LOG_ROTATION_DAYS=7
LOG_MAX_SIZE_MB=500
LOGS_STREAMING_ENABLED=true

# API
API_RATE_LIMIT=60
API_TIMEOUT=30
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080
```

**Mapping direct:**

| Kairos | CSWeb Community |
|--------|-----------------|
| `CSPRO_WEBHOOK_TOKEN` | `WEBHOOK_TOKEN` |
| `CSPRO_BREAKOUT_CRON` | `BREAKOUT_DEFAULT_CRON` |
| `CSPRO_BREAKOUT_AUTO_SEED` | `BREAKOUT_AUTO_SEED` |
| `CSPRO_WEBHOOK_TIMEOUT` | `WEBHOOK_TIMEOUT` |

---

## 6. Tests à Porter

### 6.1 Tests Unitaires Kairos

**Source:** `src/test/java/com/project/sentiment/`

**Pattern à porter:**

```java
// Kairos: CsProBreakoutServiceTest.java
@Test
public void testParseLogEntries_withErrorLevel_filtersCorrectly() {
    String content = "[2026-03-14T16:50:01+00:00] app.ERROR: Failed...\n" +
                     "[2026-03-14T16:51:00+00:00] app.INFO: Success...";

    List<CsWebLogEntry> entries = service.parseLogEntries(content, "ERROR", null);

    assertEquals(1, entries.size());
    assertEquals("ERROR", entries.get(0).getLevel());
}
```

**Port PHP (CSWeb Community):**

```php
// CSWeb Community: tests/Service/LogParserServiceTest.php
namespace Tests\AppBundle\Service;

use PHPUnit\Framework\TestCase;
use AppBundle\Service\LogParserService;

class LogParserServiceTest extends TestCase
{
    private LogParserService $service;

    protected function setUp(): void
    {
        $this->service = new LogParserService();
    }

    public function testParseLogEntries_withErrorLevel_filtersCorrectly(): void
    {
        $content = "[2026-03-14T16:50:01+00:00] app.ERROR: Failed...\n" .
                   "[2026-03-14T16:51:00+00:00] app.INFO: Success...";

        $entries = $this->service->parseLogEntries($content, 'ERROR', null);

        $this->assertCount(1, $entries);
        $this->assertEquals('ERROR', $entries[0]['level']);
    }

    public function testParseLogEntries_withSearch_filtersCorrectly(): void
    {
        $content = "[2026-03-14T16:50:01+00:00] app.ERROR: Breakout failed for EVAL_PRODUCTEURS\n" .
                   "[2026-03-14T16:51:00+00:00] app.ERROR: Database connection timeout";

        $entries = $this->service->parseLogEntries($content, null, 'breakout');

        $this->assertCount(1, $entries);
        $this->assertStringContainsString('Breakout', $entries[0]['message']);
    }
}
```

### 6.2 Tests d'Intégration

```php
// tests/Controller/BreakoutControllerTest.php
namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BreakoutControllerTest extends WebTestCase
{
    public function testTriggerBreakout_withValidDict_returnsSuccess(): void
    {
        $client = static::createClient();

        // Login to get JWT
        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'username' => 'admin',
            'password' => 'admin123'
        ]));

        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $token = $data['accessToken'];

        // Trigger breakout
        $client->request('POST', '/api/breakout/EVAL_PRODUCTEURS_USAID/trigger', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json'
        ]);

        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $result = json_decode($response->getContent(), true);
        $this->assertTrue($result['success']);
        $this->assertEquals('EVAL_PRODUCTEURS_USAID', $result['dictionary']);
        $this->assertEquals(0, $result['exitCode']);
    }
}
```

---

## 7. Commandes Symfony Réutilisables

### 7.1 Commande Breakout (Existante)

**Déjà implémentée dans CSWeb 8 PG:**

```bash
php bin/console csweb:process-cases-by-dict <DICT>
```

**Exemple:**
```bash
php bin/console csweb:process-cases-by-dict EVAL_PRODUCTEURS_USAID
```

**Code source (probable):**

```php
// src/AppBundle/Command/ProcessCasesByDictCommand.php
namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\CSPro\DictionarySchemaHelper;

class ProcessCasesByDictCommand extends Command
{
    protected static $defaultName = 'csweb:process-cases-by-dict';

    private DictionarySchemaHelper $schemaHelper;

    public function __construct(DictionarySchemaHelper $schemaHelper)
    {
        parent::__construct();
        $this->schemaHelper = $schemaHelper;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Process cases for a specific dictionary')
            ->addArgument('dictionary', InputArgument::REQUIRED, 'Dictionary name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dictionaryName = $input->getArgument('dictionary');

        $output->writeln("Processing dictionary: {$dictionaryName}");

        try {
            $this->schemaHelper->processDictionary($dictionaryName);
            $output->writeln('<info>Breakout completed successfully</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Breakout failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
```

### 7.2 Nouvelles Commandes à Créer (Inspirées Kairos)

#### A. Scheduler Runner

```php
// src/AppBundle/Command/SchedulerRunCommand.php
namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Service\SchedulerService;

class SchedulerRunCommand extends Command
{
    protected static $defaultName = 'csweb:scheduler:run';

    private SchedulerService $schedulerService;

    public function __construct(SchedulerService $schedulerService)
    {
        parent::__construct();
        $this->schedulerService = $schedulerService;
    }

    protected function configure(): void
    {
        $this->setDescription('Run the scheduler service (continuous loop)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting scheduler service...</info>');

        $this->schedulerService->run(); // Infinite loop

        return Command::SUCCESS;
    }
}
```

**Usage:**
```bash
# Lancer le scheduler (via Supervisor en prod)
php bin/console csweb:scheduler:run
```

#### B. Sync Breakout Jobs

```php
// src/AppBundle/Command/BreakoutSyncCommand.php
namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Service\BreakoutService;

class BreakoutSyncCommand extends Command
{
    protected static $defaultName = 'csweb:breakout:sync';

    private BreakoutService $breakoutService;

    public function __construct(BreakoutService $breakoutService)
    {
        parent::__construct();
        $this->breakoutService = $breakoutService;
    }

    protected function configure(): void
    {
        $this->setDescription('Synchronize dictionaries into breakout scheduler jobs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Syncing dictionaries...</info>');

        $result = $this->breakoutService->syncDictionaries();

        $output->writeln(sprintf(
            '<info>Sync complete: total=%d, created=%d, existing=%d</info>',
            $result['total'],
            $result['created'],
            $result['existing']
        ));

        return Command::SUCCESS;
    }
}
```

**Usage:**
```bash
# Créer jobs scheduler pour tous les dictionnaires
php bin/console csweb:breakout:sync
```

---

## 8. Supervisor Configuration (Scheduler)

**Inspiré de Kairos API, à adapter pour CSWeb Community:**

```ini
; /etc/supervisor/conf.d/csweb-scheduler.conf
[program:csweb-scheduler]
command=php /var/www/csweb/bin/console csweb:scheduler:run
directory=/var/www/csweb
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/csweb/var/logs/scheduler.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=10
```

**Dockerfile (Scheduler Service):**

```dockerfile
# docker/scheduler/Dockerfile
FROM php:8.0-cli-alpine

# Install dependencies
RUN apk add --no-cache postgresql-dev mysql-client supervisor

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql pdo_mysql

# Copy app
WORKDIR /var/www/csweb
COPY . .

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy supervisor config
COPY docker/scheduler/supervisord.conf /etc/supervisor/supervisord.conf

CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
```

---

## 9. Checklist Complète Migration

### Phase 1: Fondations (Semaine 1-2)

- [ ] Copier code CSWeb 8 PG (avec mods Assietou)
- [ ] Intégrer 3 webhooks PHP (breakout, log-reader, schema)
- [ ] Créer structure Symfony API (Controllers, Services)
- [ ] Setup Docker Compose (CSWeb, MySQL, PostgreSQL)
- [ ] Tests: Breakout par dictionnaire fonctionne

### Phase 2: API REST (Semaine 3-4)

- [ ] Porter `CsProBreakoutService.java` → `BreakoutService.php`
- [ ] Porter `CsProWebClient.java` → `WebhookClient.php`
- [ ] Porter `DynamicSchedulerService.java` → `SchedulerService.php`
- [ ] Créer endpoints REST (Breakout, Scheduler, Logs, Schemas)
- [ ] Tests: Tous endpoints fonctionnels

### Phase 3: Scheduler (Semaine 5-6)

- [ ] Créer table `scheduler_jobs` (pattern Kairos)
- [ ] Implémenter `SchedulerService::run()` (loop)
- [ ] Créer commande `csweb:scheduler:run`
- [ ] Créer commande `csweb:breakout:sync`
- [ ] Setup Supervisor pour scheduler
- [ ] Tests: Jobs exécutés automatiquement

### Phase 4: Logs & Monitoring (Semaine 7-8)

- [ ] Porter `parseLogEntries()` (Java → PHP)
- [ ] Créer endpoint `/api/logs` (filtres)
- [ ] Créer endpoint `/api/logs/stream` (SSE)
- [ ] Tests: Logs parsés + streaming OK

### Phase 5: Documentation (Semaine 9-10)

- [ ] Adapter `CSWEB-WEBHOOKS-GUIDE.md` → `INSTALLATION-GUIDE.md`
- [ ] Adapter `CSWEB-QUICK-REFERENCE.md` → `API-REFERENCE.md`
- [ ] Créer `BREAKOUT-GUIDE.md` (multi-DB)
- [ ] Créer `SCHEDULER-GUIDE.md` (UI + cron)
- [ ] Créer `MONITORING-GUIDE.md` (logs + metrics)

---

## 10. Résumé des Gains

### 10.1 Code Réutilisable (Estimation)

| Composant | Lignes Code Kairos | Lignes Portées | % Réutilisation |
|-----------|-------------------|----------------|-----------------|
| Webhooks PHP | 800 | 800 | 100% |
| Breakout Service | 300 | 250 | 83% |
| Scheduler Service | 400 | 350 | 87% |
| Log Parser | 150 | 150 | 100% |
| API Controllers | 500 | 400 | 80% |
| **Total Backend** | **2150** | **1950** | **~90%** |

### 10.2 Documentation Réutilisable

| Document | Pages Kairos | Pages CSWeb Community | % Réutilisation |
|----------|--------------|----------------------|-----------------|
| Webhooks Guide | 60 | 50 | 83% |
| Quick Reference | 10 | 10 | 100% |
| Scripts README | 5 | 5 | 100% |
| **Total Docs** | **75 pages** | **65 pages** | **~87%** |

### 10.3 Temps Économisé (Estimation)

| Tâche | Temps From Scratch | Temps avec Réutilisation | Gain |
|-------|-------------------|-------------------------|------|
| Webhooks | 2 semaines | 2 jours | **80%** |
| API REST | 3 semaines | 1 semaine | **67%** |
| Scheduler | 2 semaines | 1 semaine | **50%** |
| Logs Parsing | 1 semaine | 1 jour | **80%** |
| Documentation | 4 semaines | 1 semaine | **75%** |
| **Total** | **12 semaines** | **4.5 semaines** | **~63%** |

---

## 11. Conclusion

### Ce qu'on a déjà (Kairos + CSWeb 8 PG)

✅ **Breakout par dictionnaire** (Assietou Diagne)
✅ **3 Webhooks PHP sécurisés** (Kairos API)
✅ **Documentation exhaustive** (210 pages)
✅ **Patterns éprouvés** (Scheduler, Logs, API)
✅ **Tests unitaires** (90 tests Kairos)

### Ce qu'on doit créer (CSWeb Community)

➕ **Admin Panel React** (UI moderne)
➕ **Docker Compose complet** (multi-services)
➕ **Multi-SGBD support** (PostgreSQL, MySQL, SQL Server)
➕ **Site GitHub Pages** (Blog + Docs)
➕ **Communauté Discord** (Support + Contributions)

### Recommandation Finale

**Stratégie gagnante:**

1. **Copier-coller maximum** de Kairos (webhooks, scheduler, logs)
2. **Adapter** pour Symfony (Java → PHP patterns)
3. **Compléter** avec UI moderne (React)
4. **Documenter** en s'inspirant des 210 pages Kairos
5. **Lancer communauté** GitHub + Discord + YouTube

**Résultat attendu:**

- **Gain temps:** ~63% (4.5 semaines au lieu de 12)
- **Qualité:** Même niveau que Kairos (éprouvé en prod)
- **Documentation:** Même exhaustivité (réutilisation 87%)
- **Communauté:** Base solide avec REX Kairos

---

**Prochaine étape:** Créer repo `csweb-community` et commencer migration code Kairos vers Symfony.

**Auteur:** Bouna DRAME
**Date:** 14 Mars 2026
