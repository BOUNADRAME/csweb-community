# Guide d'Intégration : CSWeb Webhooks

> **Tutoriel professionnel pour intégrer des webhooks avec CSWeb Community Platform**

**Version :** 1.0.0
**Date :** 14 Mars 2026
**Auteur :** Bouna DRAME

---

## 📋 Table des Matières

1. [Introduction](#introduction)
2. [Authentification CSWeb OAuth2](#authentification-csweb-oauth2)
3. [Qu'est-ce qu'un Webhook ?](#quest-ce-quun-webhook)
4. [Webhooks Disponibles](#webhooks-disponibles)
5. [Prérequis](#prérequis)
6. [Configuration](#configuration)
7. [Implémentation](#implémentation)
8. [Sécurité](#sécurité)
9. [Testing](#testing)
10. [Troubleshooting](#troubleshooting)
11. [Exemples Complets](#exemples-complets)

---

## Introduction

Les webhooks CSWeb permettent à votre plateforme de recevoir des notifications en temps réel lorsque certains événements se produisent dans CSWeb :

- ✅ Nouveau dictionnaire synchronisé
- ✅ Cases uploadées depuis devices
- ✅ Breakout de données complété
- ✅ Erreurs de traitement

Ce guide vous montre comment implémenter et sécuriser ces webhooks.

---

## Authentification CSWeb OAuth2

### ⚠️ Prérequis Important

Avant d'utiliser les webhooks ou l'API CSWeb, vous devez vous authentifier via **OAuth2** pour obtenir un **access_token**.

### Guide Complet

📖 **Consultez le guide dédié :** [CSWEB-OAUTH-AUTHENTICATION.md](CSWEB-OAUTH-AUTHENTICATION.md)

Ce guide couvre :
- ✅ Obtenir access_token et refresh_token
- ✅ Configuration .env complète
- ✅ Implémentation Spring Boot, Laravel, Express.js
- ✅ Gestion automatique du refresh
- ✅ Cache et thread-safety
- ✅ Gestion des erreurs 401

### Résumé Rapide

**1. Obtenir Token**

```bash
curl -X POST http://localhost:8080/api/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password" \
  -d "username=api_user" \
  -d "password=SecurePassword123!"
```

**Réponse :**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "refresh_token": "def50200a1b2c3d4e5f6...",
  "expires_in": 3600
}
```

**2. Utiliser Token**

```bash
curl -X GET http://localhost:8080/api/dictionaries \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

**3. Refresh Token (avant expiration)**

```bash
curl -X POST http://localhost:8080/api/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=refresh_token" \
  -d "refresh_token=def50200a1b2c3d4e5f6..."
```

### Configuration .env

```bash
# CSWeb OAuth2
CSWEB_API_URL=http://localhost:8080/api
CSWEB_USERNAME=api_user
CSWEB_PASSWORD=SecurePassword123!
CSWEB_GRANT_TYPE=password

# Webhooks (section suivante)
WEBHOOK_ENABLED=true
WEBHOOK_TOKEN=your_webhook_token_here
```

---

## Qu'est-ce qu'un Webhook ?

### Concept

Un **webhook** est une méthode permettant à une application (CSWeb) d'envoyer des notifications HTTP automatiques à une autre application (votre backend) lorsqu'un événement spécifique se produit.

### Workflow

```
┌─────────────┐         Événement          ┌─────────────┐
│             │      (nouveau case)         │             │
│   CSWeb     │ ──────────────────────────> │ Votre API   │
│  (Source)   │                             │ (Endpoint)  │
│             │ <────────────────────────── │             │
└─────────────┘     HTTP 200 OK             └─────────────┘
```

### Avantages

- ⚡ **Temps réel** : Notification immédiate
- 🔄 **Automatisation** : Pas de polling
- 📊 **Scalabilité** : Décharge CSWeb du traitement
- 🔗 **Intégration** : Connecte CSWeb à vos systèmes

---

## Webhooks Disponibles

### 1. Breakout Webhook

**Événement :** Breakout de données complété pour un dictionnaire

**URL :** `POST /api/webhooks/breakout`

**Payload :**
```json
{
  "event": "breakout.completed",
  "dictionary_name": "SURVEY_DICT",
  "dictionary_label": "survey",
  "total_cases": 1500,
  "processed_cases": 1500,
  "failed_cases": 0,
  "database_type": "postgresql",
  "database_name": "csweb_analytics",
  "tables_created": [
    "survey_cases",
    "survey_level_1",
    "survey_level_2",
    "survey_record_001"
  ],
  "timestamp": "2026-03-14T10:30:00Z"
}
```

### 2. Dictionary Schema Webhook

**Événement :** Nouveau dictionnaire synchronisé

**URL :** `POST /api/webhooks/dictionary-schema`

**Payload :**
```json
{
  "event": "dictionary.synchronized",
  "dictionary_name": "CENSUS_DICT",
  "dictionary_label": "census",
  "version": "2.0",
  "schema": {
    "levels": 2,
    "records": 3,
    "items": 45
  },
  "timestamp": "2026-03-14T09:15:00Z"
}
```

### 3. Case Upload Webhook

**Événement :** Nouveaux cases uploadés depuis devices

**URL :** `POST /api/webhooks/case-upload`

**Payload :**
```json
{
  "event": "cases.uploaded",
  "dictionary_name": "HEALTH_DICT",
  "device_id": "TABLET_001",
  "cases_count": 50,
  "upload_status": "success",
  "timestamp": "2026-03-14T11:45:00Z"
}
```

### 4. Error Webhook

**Événement :** Erreur de traitement

**URL :** `POST /api/webhooks/error`

**Payload :**
```json
{
  "event": "processing.error",
  "dictionary_name": "SURVEY_DICT",
  "error_type": "database_connection",
  "error_message": "PostgreSQL connection refused",
  "stack_trace": "...",
  "timestamp": "2026-03-14T12:00:00Z"
}
```

---

## Prérequis

### Backend

- **Framework** : Spring Boot, Laravel, Express.js, FastAPI, etc.
- **HTTPS** : Obligatoire en production
- **Port public** : Accessible depuis CSWeb

### CSWeb

- **Version** : CSWeb 8.0+
- **Configuration** : `.env` avec webhook URLs
- **Authentification** : Token Bearer

---

## Configuration

### Étape 1 : Configurer les Endpoints

Éditer `.env` dans votre installation CSWeb :

```bash
# Webhook Configuration
WEBHOOK_ENABLED=true
WEBHOOK_BASE_URL=https://votre-domaine.com/api/webhooks
WEBHOOK_TOKEN=your_secure_token_here

# Webhooks individuels
WEBHOOK_BREAKOUT_ENABLED=true
WEBHOOK_DICTIONARY_ENABLED=true
WEBHOOK_CASE_UPLOAD_ENABLED=true
WEBHOOK_ERROR_ENABLED=true

# Retry Configuration
WEBHOOK_RETRY_ATTEMPTS=3
WEBHOOK_RETRY_DELAY=5000
WEBHOOK_TIMEOUT=30000
```

### Étape 2 : Générer Token Sécurisé

```bash
# Générer token fort
openssl rand -base64 32

# Exemple de résultat
# K8x9mNpQr2tUvWyZ5aB7cD1eF3gH6jL4
```

### Étape 3 : Configurer Votre Backend

**Exemple Spring Boot (application.yml) :**

```yaml
webhook:
  csweb:
    token: K8x9mNpQr2tUvWyZ5aB7cD1eF3gH6jL4
    allowed-ips:
      - 192.168.1.100  # IP du serveur CSWeb
```

**Exemple Laravel (.env) :**

```bash
CSWEB_WEBHOOK_TOKEN=K8x9mNpQr2tUvWyZ5aB7cD1eF3gH6jL4
CSWEB_ALLOWED_IP=192.168.1.100
```

---

## Implémentation

### Exemple 1 : Spring Boot (Java)

#### Controller

```java
package com.example.api.controller;

import org.springframework.web.bind.annotation.*;
import org.springframework.http.ResponseEntity;
import lombok.extern.slf4j.Slf4j;

@Slf4j
@RestController
@RequestMapping("/api/webhooks")
public class CSWebWebhookController {

    private static final String WEBHOOK_TOKEN = "K8x9mNpQr2tUvWyZ5aB7cD1eF3gH6jL4";

    /**
     * Webhook: Breakout complété
     */
    @PostMapping("/breakout")
    public ResponseEntity<?> handleBreakout(
        @RequestHeader("Authorization") String authHeader,
        @RequestBody BreakoutWebhookPayload payload
    ) {
        // 1. Vérifier token
        if (!isValidToken(authHeader)) {
            log.warn("Invalid webhook token received");
            return ResponseEntity.status(401).body("Unauthorized");
        }

        // 2. Logger l'événement
        log.info("Breakout completed: dictionary={}, cases={}",
            payload.getDictionaryName(),
            payload.getTotalCases()
        );

        // 3. Traiter l'événement
        processBreakout(payload);

        // 4. Répondre rapidement (< 5 secondes)
        return ResponseEntity.ok().body(Map.of("status", "received"));
    }

    /**
     * Webhook: Dictionnaire synchronisé
     */
    @PostMapping("/dictionary-schema")
    public ResponseEntity<?> handleDictionarySchema(
        @RequestHeader("Authorization") String authHeader,
        @RequestBody DictionarySchemaPayload payload
    ) {
        if (!isValidToken(authHeader)) {
            return ResponseEntity.status(401).body("Unauthorized");
        }

        log.info("Dictionary synchronized: {}", payload.getDictionaryName());
        processDictionary(payload);

        return ResponseEntity.ok().body(Map.of("status", "received"));
    }

    /**
     * Webhook: Cases uploadés
     */
    @PostMapping("/case-upload")
    public ResponseEntity<?> handleCaseUpload(
        @RequestHeader("Authorization") String authHeader,
        @RequestBody CaseUploadPayload payload
    ) {
        if (!isValidToken(authHeader)) {
            return ResponseEntity.status(401).body("Unauthorized");
        }

        log.info("Cases uploaded: dictionary={}, count={}",
            payload.getDictionaryName(),
            payload.getCasesCount()
        );

        processCaseUpload(payload);

        return ResponseEntity.ok().body(Map.of("status", "received"));
    }

    /**
     * Webhook: Erreur
     */
    @PostMapping("/error")
    public ResponseEntity<?> handleError(
        @RequestHeader("Authorization") String authHeader,
        @RequestBody ErrorWebhookPayload payload
    ) {
        if (!isValidToken(authHeader)) {
            return ResponseEntity.status(401).body("Unauthorized");
        }

        log.error("CSWeb error: type={}, message={}",
            payload.getErrorType(),
            payload.getErrorMessage()
        );

        processError(payload);

        return ResponseEntity.ok().body(Map.of("status", "received"));
    }

    // Helpers

    private boolean isValidToken(String authHeader) {
        if (authHeader == null || !authHeader.startsWith("Bearer ")) {
            return false;
        }
        String token = authHeader.substring(7);
        return WEBHOOK_TOKEN.equals(token);
    }

    private void processBreakout(BreakoutWebhookPayload payload) {
        // Traitement asynchrone recommandé
        CompletableFuture.runAsync(() -> {
            // Mettre à jour votre base de données
            // Déclencher analyses
            // Envoyer notifications
        });
    }

    private void processDictionary(DictionarySchemaPayload payload) {
        // Traitement asynchrone
    }

    private void processCaseUpload(CaseUploadPayload payload) {
        // Traitement asynchrone
    }

    private void processError(ErrorWebhookPayload payload) {
        // Alertes, notifications
    }
}
```

#### DTOs

```java
package com.example.api.dto;

import lombok.Data;
import java.time.Instant;
import java.util.List;

@Data
public class BreakoutWebhookPayload {
    private String event;
    private String dictionaryName;
    private String dictionaryLabel;
    private Integer totalCases;
    private Integer processedCases;
    private Integer failedCases;
    private String databaseType;
    private String databaseName;
    private List<String> tablesCreated;
    private Instant timestamp;
}

@Data
public class DictionarySchemaPayload {
    private String event;
    private String dictionaryName;
    private String dictionaryLabel;
    private String version;
    private SchemaInfo schema;
    private Instant timestamp;

    @Data
    public static class SchemaInfo {
        private Integer levels;
        private Integer records;
        private Integer items;
    }
}

@Data
public class CaseUploadPayload {
    private String event;
    private String dictionaryName;
    private String deviceId;
    private Integer casesCount;
    private String uploadStatus;
    private Instant timestamp;
}

@Data
public class ErrorWebhookPayload {
    private String event;
    private String dictionaryName;
    private String errorType;
    private String errorMessage;
    private String stackTrace;
    private Instant timestamp;
}
```

### Exemple 2 : Laravel (PHP)

#### Route

```php
// routes/api.php
use App\Http\Controllers\CSWebWebhookController;

Route::prefix('webhooks')->group(function () {
    Route::post('/breakout', [CSWebWebhookController::class, 'breakout']);
    Route::post('/dictionary-schema', [CSWebWebhookController::class, 'dictionarySchema']);
    Route::post('/case-upload', [CSWebWebhookController::class, 'caseUpload']);
    Route::post('/error', [CSWebWebhookController::class, 'error']);
});
```

#### Controller

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessBreakoutWebhook;

class CSWebWebhookController extends Controller
{
    private const WEBHOOK_TOKEN = 'K8x9mNpQr2tUvWyZ5aB7cD1eF3gH6jL4';

    /**
     * Breakout complété
     */
    public function breakout(Request $request)
    {
        // 1. Valider token
        if (!$this->isValidToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 2. Valider payload
        $validated = $request->validate([
            'event' => 'required|string',
            'dictionary_name' => 'required|string',
            'total_cases' => 'required|integer',
            'processed_cases' => 'required|integer',
        ]);

        // 3. Logger
        Log::info('Breakout webhook received', $validated);

        // 4. Traiter asynchrone (recommandé)
        ProcessBreakoutWebhook::dispatch($validated);

        // 5. Réponse rapide
        return response()->json(['status' => 'received']);
    }

    /**
     * Dictionnaire synchronisé
     */
    public function dictionarySchema(Request $request)
    {
        if (!$this->isValidToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'event' => 'required|string',
            'dictionary_name' => 'required|string',
            'version' => 'nullable|string',
        ]);

        Log::info('Dictionary schema webhook received', $validated);

        // Traiter...

        return response()->json(['status' => 'received']);
    }

    /**
     * Cases uploadés
     */
    public function caseUpload(Request $request)
    {
        if (!$this->isValidToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'event' => 'required|string',
            'dictionary_name' => 'required|string',
            'cases_count' => 'required|integer',
        ]);

        Log::info('Case upload webhook received', $validated);

        return response()->json(['status' => 'received']);
    }

    /**
     * Erreur
     */
    public function error(Request $request)
    {
        if (!$this->isValidToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'event' => 'required|string',
            'error_type' => 'required|string',
            'error_message' => 'required|string',
        ]);

        Log::error('CSWeb error webhook', $validated);

        // Alertes...

        return response()->json(['status' => 'received']);
    }

    /**
     * Valider token Bearer
     */
    private function isValidToken(Request $request): bool
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }

        $token = substr($authHeader, 7);
        return $token === self::WEBHOOK_TOKEN;
    }
}
```

#### Job Asynchrone

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBreakoutWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle()
    {
        // Traitement long
        // - Mettre à jour statistiques
        // - Générer rapports
        // - Envoyer notifications

        \Log::info('Processing breakout webhook', $this->payload);
    }
}
```

### Exemple 3 : Express.js (Node.js)

```javascript
const express = require('express');
const app = express();

const WEBHOOK_TOKEN = 'K8x9mNpQr2tUvWyZ5aB7cD1eF3gH6jL4';

// Middleware
app.use(express.json());

// Middleware d'authentification
const authenticateWebhook = (req, res, next) => {
    const authHeader = req.headers.authorization;

    if (!authHeader || !authHeader.startsWith('Bearer ')) {
        return res.status(401).json({ error: 'Unauthorized' });
    }

    const token = authHeader.substring(7);
    if (token !== WEBHOOK_TOKEN) {
        return res.status(401).json({ error: 'Unauthorized' });
    }

    next();
};

// Webhooks
app.post('/api/webhooks/breakout', authenticateWebhook, async (req, res) => {
    const payload = req.body;

    console.log('Breakout completed:', payload);

    // Traiter asynchrone
    setImmediate(async () => {
        // Traitement long...
    });

    // Réponse rapide
    res.json({ status: 'received' });
});

app.post('/api/webhooks/dictionary-schema', authenticateWebhook, async (req, res) => {
    const payload = req.body;
    console.log('Dictionary synchronized:', payload);
    res.json({ status: 'received' });
});

app.post('/api/webhooks/case-upload', authenticateWebhook, async (req, res) => {
    const payload = req.body;
    console.log('Cases uploaded:', payload);
    res.json({ status: 'received' });
});

app.post('/api/webhooks/error', authenticateWebhook, async (req, res) => {
    const payload = req.body;
    console.error('CSWeb error:', payload);
    res.json({ status: 'received' });
});

app.listen(3000, () => {
    console.log('Webhook server running on port 3000');
});
```

---

## Sécurité

### 1. Authentification Bearer Token

**Toujours utiliser un token fort :**

```bash
# Générer
openssl rand -base64 32

# Exemple
K8x9mNpQr2tUvWyZ5aB7cD1eF3gH6jL4
```

**Valider côté backend :**

```java
// Java
private boolean isValidToken(String authHeader) {
    if (authHeader == null || !authHeader.startsWith("Bearer ")) {
        return false;
    }
    String token = authHeader.substring(7);
    return WEBHOOK_TOKEN.equals(token);
}
```

### 2. Whitelist IP

**Autoriser uniquement l'IP du serveur CSWeb :**

```java
// Spring Boot
@Component
public class WebhookSecurityFilter implements Filter {

    private static final String ALLOWED_IP = "192.168.1.100";

    @Override
    public void doFilter(ServletRequest request, ServletResponse response, FilterChain chain) {
        HttpServletRequest httpRequest = (HttpServletRequest) request;
        String clientIp = httpRequest.getRemoteAddr();

        if (!ALLOWED_IP.equals(clientIp)) {
            ((HttpServletResponse) response).setStatus(403);
            return;
        }

        chain.doFilter(request, response);
    }
}
```

### 3. HTTPS Obligatoire

**En production, utiliser uniquement HTTPS :**

```nginx
# Nginx
server {
    listen 443 ssl http2;
    server_name api.example.com;

    ssl_certificate /etc/ssl/certs/api.crt;
    ssl_certificate_key /etc/ssl/private/api.key;

    location /api/webhooks {
        proxy_pass http://localhost:8080;
    }
}
```

### 4. Validation Payload

**Toujours valider les données reçues :**

```java
// Java + Validation API
@PostMapping("/breakout")
public ResponseEntity<?> handleBreakout(@Valid @RequestBody BreakoutWebhookPayload payload) {
    // Validation automatique via @NotNull, @Min, etc.
}

@Data
public class BreakoutWebhookPayload {
    @NotBlank
    private String event;

    @NotBlank
    private String dictionaryName;

    @Min(0)
    private Integer totalCases;
}
```

### 5. Rate Limiting

**Limiter le nombre de requêtes :**

```java
// Spring Boot + Bucket4j
@RateLimiter(name = "webhook", fallbackMethod = "rateLimitFallback")
@PostMapping("/breakout")
public ResponseEntity<?> handleBreakout(...) {
    // ...
}

public ResponseEntity<?> rateLimitFallback(Exception e) {
    return ResponseEntity.status(429).body("Too many requests");
}
```

---

## Testing

### Test Local avec curl

```bash
# Test breakout webhook
curl -X POST http://localhost:8080/api/webhooks/breakout \
  -H "Authorization: Bearer K8x9mNpQr2tUvWyZ5aB7cD1eF3gH6jL4" \
  -H "Content-Type: application/json" \
  -d '{
    "event": "breakout.completed",
    "dictionary_name": "TEST_DICT",
    "dictionary_label": "test",
    "total_cases": 100,
    "processed_cases": 100,
    "failed_cases": 0,
    "database_type": "postgresql",
    "database_name": "csweb_analytics",
    "tables_created": ["test_cases", "test_level_1"],
    "timestamp": "2026-03-14T10:00:00Z"
  }'

# Réponse attendue
# {"status":"received"}
```

### Test Authentification

```bash
# Sans token (doit échouer)
curl -X POST http://localhost:8080/api/webhooks/breakout \
  -H "Content-Type: application/json" \
  -d '{}'

# Réponse attendue: 401 Unauthorized

# Token invalide (doit échouer)
curl -X POST http://localhost:8080/api/webhooks/breakout \
  -H "Authorization: Bearer INVALID_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'

# Réponse attendue: 401 Unauthorized
```

### Test avec Postman

**1. Créer collection "CSWeb Webhooks"**

**2. Ajouter request "Breakout Webhook":**
- Method: `POST`
- URL: `http://localhost:8080/api/webhooks/breakout`
- Headers:
  - `Authorization`: `Bearer K8x9mNpQr2tUvWyZ5aB7cD1eF3gH6jL4`
  - `Content-Type`: `application/json`
- Body (raw JSON):
```json
{
  "event": "breakout.completed",
  "dictionary_name": "TEST_DICT",
  "dictionary_label": "test",
  "total_cases": 100,
  "processed_cases": 100,
  "failed_cases": 0,
  "database_type": "postgresql",
  "database_name": "csweb_analytics",
  "tables_created": ["test_cases"],
  "timestamp": "2026-03-14T10:00:00Z"
}
```

**3. Envoyer → Vérifier réponse 200 OK**

---

## Troubleshooting

### Problème 1 : 401 Unauthorized

**Symptôme :**
```json
{"error": "Unauthorized"}
```

**Solutions :**

1. Vérifier token dans `.env` CSWeb :
```bash
cat .env | grep WEBHOOK_TOKEN
```

2. Vérifier token dans backend :
```bash
# Doivent être identiques
```

3. Vérifier format Authorization header :
```bash
# Correct
Authorization: Bearer K8x9mNpQr2tUvWyZ5aB7cD1eF3gH6jL4

# Incorrect (manque "Bearer ")
Authorization: K8x9mNpQr2tUvWyZ5aB7cD1eF3gH6jL4
```

### Problème 2 : 404 Not Found

**Symptôme :**
```
404 Not Found
```

**Solutions :**

1. Vérifier URL dans `.env` CSWeb :
```bash
WEBHOOK_BASE_URL=https://api.example.com/api/webhooks
#                                          ^^^^^^^^^^^^
#                                          Route correcte ?
```

2. Vérifier route backend :
```java
// Spring Boot
@PostMapping("/api/webhooks/breakout")  // Doit correspondre
```

### Problème 3 : Webhook timeout

**Symptôme :**
CSWeb logs :
```
Webhook timeout after 30 seconds
```

**Solutions :**

1. **Traiter asynchrone** (recommandé) :
```java
@PostMapping("/breakout")
public ResponseEntity<?> handleBreakout(@RequestBody BreakoutWebhookPayload payload) {
    // 1. Répondre IMMÉDIATEMENT
    CompletableFuture.runAsync(() -> {
        // 2. Traitement long ici
        processBreakout(payload);
    });

    // 3. Réponse en < 5 secondes
    return ResponseEntity.ok().body(Map.of("status", "received"));
}
```

2. **Augmenter timeout** (si nécessaire) :
```bash
# .env CSWeb
WEBHOOK_TIMEOUT=60000  # 60 secondes
```

### Problème 4 : Données manquantes

**Symptôme :**
```
NullPointerException: payload.dictionaryName is null
```

**Solutions :**

1. Vérifier validation :
```java
@NotBlank
private String dictionaryName;
```

2. Logger payload brut :
```java
@PostMapping("/breakout")
public ResponseEntity<?> handleBreakout(@RequestBody String rawPayload) {
    log.info("Raw payload: {}", rawPayload);
    // ...
}
```

---

## Exemples Complets

### Exemple : Système de Notification

**Cas d'usage :** Envoyer email quand breakout complété

**Backend Spring Boot :**

```java
@Service
public class WebhookNotificationService {

    @Autowired
    private EmailService emailService;

    public void handleBreakoutCompleted(BreakoutWebhookPayload payload) {
        String subject = String.format("Breakout complété : %s", payload.getDictionaryName());

        String body = String.format("""
            Le breakout du dictionnaire %s est terminé.

            Statistiques :
            - Total cases : %d
            - Cases traités : %d
            - Cases échoués : %d
            - Base de données : %s (%s)
            - Tables créées : %s

            Timestamp : %s
            """,
            payload.getDictionaryName(),
            payload.getTotalCases(),
            payload.getProcessedCases(),
            payload.getFailedCases(),
            payload.getDatabaseName(),
            payload.getDatabaseType(),
            String.join(", ", payload.getTablesCreated()),
            payload.getTimestamp()
        );

        emailService.sendEmail("admin@example.com", subject, body);
    }
}
```

### Exemple : Mise à jour Dashboard

**Cas d'usage :** Mettre à jour statistiques temps réel

**Backend Laravel :**

```php
<?php

namespace App\Jobs;

use App\Models\Dictionary;
use App\Events\BreakoutCompleted;

class ProcessBreakoutWebhook implements ShouldQueue
{
    private array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle()
    {
        // 1. Mettre à jour statistiques
        Dictionary::updateOrCreate(
            ['name' => $this->payload['dictionary_name']],
            [
                'label' => $this->payload['dictionary_label'],
                'total_cases' => $this->payload['total_cases'],
                'processed_cases' => $this->payload['processed_cases'],
                'last_breakout_at' => now(),
            ]
        );

        // 2. Broadcast event temps réel (WebSocket)
        event(new BreakoutCompleted($this->payload));

        // 3. Logger
        \Log::info('Dashboard updated', $this->payload);
    }
}
```

**Frontend Vue.js (écoute WebSocket) :**

```javascript
// Vue component
export default {
    mounted() {
        Echo.channel('breakouts')
            .listen('BreakoutCompleted', (event) => {
                console.log('Breakout completed:', event);
                this.updateDashboard(event);
            });
    },

    methods: {
        updateDashboard(event) {
            // Mettre à jour statistiques en temps réel
            this.stats.totalCases = event.total_cases;
            this.stats.processedCases = event.processed_cases;

            // Notification toast
            this.$toast.success(`Breakout ${event.dictionary_name} complété !`);
        }
    }
}
```

---

## Ressources

### Documentation Officielle

- **CSWeb API** : https://csprousers.org/help/CSWeb/introduction.html
- **Webhooks Concepts** : https://en.wikipedia.org/wiki/Webhook

### Outils

- **Postman** : Tester webhooks localement
- **ngrok** : Exposer localhost pour tests
- **RequestBin** : Inspecter payloads

### Commandes Utiles

```bash
# Exposer localhost avec ngrok (tests)
ngrok http 8080

# Tester webhook avec curl
curl -X POST URL -H "Authorization: Bearer TOKEN" -d @payload.json

# Voir logs CSWeb webhooks
docker-compose logs -f csweb | grep webhook
```

---

**CSWeb Community Platform - Webhooks Integration**

Made with ❤️ by Bouna DRAME
