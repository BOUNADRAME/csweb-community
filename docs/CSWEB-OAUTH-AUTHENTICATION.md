# Guide d'Authentification : CSWeb OAuth2

> **Tutoriel complet pour s'authentifier et accéder à l'API CSWeb**

**Version :** 1.0.0
**Date :** 14 Mars 2026
**Auteur :** Bouna DRAME

---

## 📋 Table des Matières

1. [Introduction](#introduction)
2. [Architecture OAuth2](#architecture-oauth2)
3. [Configuration Initiale](#configuration-initiale)
4. [Obtenir un Token d'Accès](#obtenir-un-token-daccès)
5. [Refresh Token](#refresh-token)
6. [Utiliser le Token](#utiliser-le-token)
7. [Implémentation Backend](#implémentation-backend)
8. [Gestion des Erreurs](#gestion-des-erreurs)
9. [Best Practices](#best-practices)
10. [Exemples Complets](#exemples-complets)

---

## Introduction

Pour accéder à l'API CSWeb et interagir avec les données (dictionnaires, cases, fichiers), vous devez vous authentifier via **OAuth2 Password Grant**.

### Workflow d'Authentification

```
┌──────────────┐                                    ┌──────────────┐
│              │  1. POST /api/token                │              │
│  Votre App   │  ──────────────────────────────>  │   CSWeb      │
│              │     username + password            │              │
│              │                                     │              │
│              │  <──────────────────────────────   │              │
│              │  2. access_token + refresh_token   │              │
│              │                                     │              │
│              │  3. GET /api/dictionaries          │              │
│              │  ──────────────────────────────>  │              │
│              │     Authorization: Bearer {token}  │              │
│              │                                     │              │
│              │  <──────────────────────────────   │              │
│              │  4. Data (JSON)                    │              │
└──────────────┘                                    └──────────────┘
```

---

## Architecture OAuth2

### Flux Password Grant

CSWeb utilise le **Resource Owner Password Credentials Grant** (OAuth2 RFC 6749).

**Étapes :**

1. **Demander token** avec `username` + `password`
2. **Recevoir** `access_token` + `refresh_token`
3. **Utiliser** `access_token` pour requêtes API
4. **Renouveler** avec `refresh_token` quand expiré

### Tokens

| Token | Durée de vie | Usage |
|-------|--------------|-------|
| **Access Token** | 1 heure (3600s) | Autoriser requêtes API |
| **Refresh Token** | 30 jours | Renouveler access_token |

---

## Configuration Initiale

### Étape 1 : Créer Utilisateur CSWeb

**Via Interface Web :**

1. Se connecter à CSWeb : `http://localhost:8080`
2. Administration → Users → Add User
3. Remplir :
   - Username : `api_user`
   - Password : `SecurePassword123!`
   - Role : `API` ou `Administrator`
4. Sauvegarder

**Via MySQL Direct (dev uniquement) :**

```sql
-- Se connecter à MySQL CSWeb
mysql -u root -p csweb_metadata

-- Créer utilisateur API
INSERT INTO cspro_users (username, password, role, created_at)
VALUES (
    'api_user',
    '$2y$10$hashed_password_here',  -- Utiliser password_hash() en PHP
    'API',
    NOW()
);
```

### Étape 2 : Configuration .env

**Backend Application (.env) :**

```bash
# CSWeb API Configuration
CSWEB_API_URL=http://localhost:8080/api
CSWEB_USERNAME=api_user
CSWEB_PASSWORD=SecurePassword123!

# OAuth2
CSWEB_GRANT_TYPE=password
CSWEB_TOKEN_ENDPOINT=/token
CSWEB_TOKEN_EXPIRATION=3600
```

---

## Obtenir un Token d'Accès

### Requête HTTP

**Endpoint :** `POST /api/token`

**Headers :**
```
Content-Type: application/x-www-form-urlencoded
```

**Body (form-data) :**
```
grant_type=password
username=api_user
password=SecurePassword123!
```

**Exemple curl :**

```bash
curl -X POST http://localhost:8080/api/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password" \
  -d "username=api_user" \
  -d "password=SecurePassword123!"
```

### Réponse Succès

**HTTP 200 OK**

```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "def50200a1b2c3d4e5f6..."
}
```

**Champs :**

- `access_token` : Token JWT à utiliser pour requêtes API
- `token_type` : Toujours "Bearer"
- `expires_in` : Durée de validité en secondes (3600 = 1h)
- `refresh_token` : Token pour renouvellement

### Réponse Erreur

**HTTP 401 Unauthorized**

```json
{
  "error": "invalid_grant",
  "error_description": "The user credentials were incorrect."
}
```

**Causes courantes :**
- Username ou password incorrect
- Compte utilisateur désactivé
- Permissions insuffisantes

---

## Refresh Token

### Quand renouveler ?

- ✅ **Avant expiration** : Renouveler 5 minutes avant `expires_in`
- ✅ **Après 401** : Si requête API retourne 401, renouveler
- ❌ **Jamais** : Ne pas attendre l'expiration complète

### Requête Refresh

**Endpoint :** `POST /api/token`

**Body (form-data) :**
```
grant_type=refresh_token
refresh_token=def50200a1b2c3d4e5f6...
```

**Exemple curl :**

```bash
curl -X POST http://localhost:8080/api/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=refresh_token" \
  -d "refresh_token=def50200a1b2c3d4e5f6..."
```

### Réponse Refresh

**HTTP 200 OK**

```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "abc12345new_refresh_token..."
}
```

**Important :**
- 🔑 Nouveau `access_token` généré
- 🔑 Nouveau `refresh_token` généré
- ⚠️ Ancien `refresh_token` **invalidé**

---

## Utiliser le Token

### Header Authorization

**Format :**
```
Authorization: Bearer {access_token}
```

**Exemple curl :**

```bash
curl -X GET http://localhost:8080/api/dictionaries \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

### Exemples Requêtes API

#### 1. Lister Dictionnaires

```bash
curl -X GET http://localhost:8080/api/dictionaries \
  -H "Authorization: Bearer {access_token}"
```

**Réponse :**
```json
[
  {
    "name": "SURVEY_DICT",
    "label": "survey",
    "version": "1.0"
  },
  {
    "name": "CENSUS_DICT",
    "label": "census",
    "version": "2.0"
  }
]
```

#### 2. Télécharger Fichier

```bash
curl -X GET http://localhost:8080/api/files/media/photo_001.jpg/content \
  -H "Authorization: Bearer {access_token}" \
  --output photo_001.jpg
```

#### 3. Récupérer Cases

```bash
curl -X GET http://localhost:8080/api/dictionaries/SURVEY_DICT/cases \
  -H "Authorization: Bearer {access_token}"
```

---

## Implémentation Backend

### Exemple 1 : Spring Boot (Java)

#### Configuration

```java
// src/main/resources/application.yml
csweb:
  api:
    url: http://localhost:8080/api
    username: api_user
    password: SecurePassword123!
    grant-type: password
```

#### Properties Class

```java
package com.example.config;

import lombok.Data;
import org.springframework.boot.context.properties.ConfigurationProperties;
import org.springframework.stereotype.Component;

@Data
@Component
@ConfigurationProperties(prefix = "csweb.api")
public class CSWebProperties {
    private String url;
    private String username;
    private String password;
    private String grantType = "password";
}
```

#### Token Response DTO

```java
package com.example.dto;

import com.fasterxml.jackson.annotation.JsonProperty;
import lombok.Data;

@Data
public class CSWebTokenResponse {
    @JsonProperty("access_token")
    private String accessToken;

    @JsonProperty("token_type")
    private String tokenType;

    @JsonProperty("expires_in")
    private Integer expiresIn;

    @JsonProperty("refresh_token")
    private String refreshToken;
}
```

#### Service d'Authentification

```java
package com.example.service;

import com.example.config.CSWebProperties;
import com.example.dto.CSWebTokenResponse;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.*;
import org.springframework.stereotype.Service;
import org.springframework.util.LinkedMultiValueMap;
import org.springframework.util.MultiValueMap;
import org.springframework.web.client.RestTemplate;

import java.time.Instant;
import java.util.concurrent.locks.ReentrantLock;

@Slf4j
@Service
@RequiredArgsConstructor
public class CSWebAuthService {

    private final CSWebProperties cswebProperties;
    private final RestTemplate restTemplate;

    private String currentAccessToken;
    private String currentRefreshToken;
    private Instant tokenExpiresAt;
    private final ReentrantLock lock = new ReentrantLock();

    /**
     * Obtenir access token valide (avec auto-refresh)
     */
    public String getAccessToken() {
        lock.lock();
        try {
            // Vérifier si token existe et est valide
            if (isTokenValid()) {
                return currentAccessToken;
            }

            // Refresh si refresh_token existe
            if (currentRefreshToken != null) {
                log.info("Refreshing access token");
                refreshAccessToken();
            } else {
                // Première authentification
                log.info("Authenticating with CSWeb");
                authenticate();
            }

            return currentAccessToken;

        } finally {
            lock.unlock();
        }
    }

    /**
     * Authentification initiale
     */
    private void authenticate() {
        String tokenUrl = cswebProperties.getUrl() + "/token";

        // Préparer request
        HttpHeaders headers = new HttpHeaders();
        headers.setContentType(MediaType.APPLICATION_FORM_URLENCODED);

        MultiValueMap<String, String> body = new LinkedMultiValueMap<>();
        body.add("grant_type", cswebProperties.getGrantType());
        body.add("username", cswebProperties.getUsername());
        body.add("password", cswebProperties.getPassword());

        HttpEntity<MultiValueMap<String, String>> request = new HttpEntity<>(body, headers);

        // Envoyer requête
        ResponseEntity<CSWebTokenResponse> response = restTemplate.postForEntity(
            tokenUrl,
            request,
            CSWebTokenResponse.class
        );

        if (response.getStatusCode() == HttpStatus.OK && response.getBody() != null) {
            updateTokens(response.getBody());
            log.info("Successfully authenticated with CSWeb");
        } else {
            throw new RuntimeException("Failed to authenticate with CSWeb");
        }
    }

    /**
     * Renouveler access token
     */
    private void refreshAccessToken() {
        String tokenUrl = cswebProperties.getUrl() + "/token";

        HttpHeaders headers = new HttpHeaders();
        headers.setContentType(MediaType.APPLICATION_FORM_URLENCODED);

        MultiValueMap<String, String> body = new LinkedMultiValueMap<>();
        body.add("grant_type", "refresh_token");
        body.add("refresh_token", currentRefreshToken);

        HttpEntity<MultiValueMap<String, String>> request = new HttpEntity<>(body, headers);

        try {
            ResponseEntity<CSWebTokenResponse> response = restTemplate.postForEntity(
                tokenUrl,
                request,
                CSWebTokenResponse.class
            );

            if (response.getStatusCode() == HttpStatus.OK && response.getBody() != null) {
                updateTokens(response.getBody());
                log.info("Successfully refreshed access token");
            } else {
                // Fallback: ré-authentifier
                log.warn("Refresh failed, re-authenticating");
                currentRefreshToken = null;
                authenticate();
            }
        } catch (Exception e) {
            // Fallback: ré-authentifier
            log.error("Refresh error, re-authenticating", e);
            currentRefreshToken = null;
            authenticate();
        }
    }

    /**
     * Mettre à jour tokens
     */
    private void updateTokens(CSWebTokenResponse tokenResponse) {
        this.currentAccessToken = tokenResponse.getAccessToken();
        this.currentRefreshToken = tokenResponse.getRefreshToken();

        // Calculer expiration (expires_in - 5 minutes de marge)
        int expiresIn = tokenResponse.getExpiresIn() != null ? tokenResponse.getExpiresIn() : 3600;
        this.tokenExpiresAt = Instant.now().plusSeconds(expiresIn - 300);
    }

    /**
     * Vérifier si token est valide
     */
    private boolean isTokenValid() {
        return currentAccessToken != null
            && tokenExpiresAt != null
            && Instant.now().isBefore(tokenExpiresAt);
    }

    /**
     * Forcer refresh (utile pour tests)
     */
    public void forceRefresh() {
        lock.lock();
        try {
            if (currentRefreshToken != null) {
                refreshAccessToken();
            } else {
                authenticate();
            }
        } finally {
            lock.unlock();
        }
    }

    /**
     * Invalider token (logout)
     */
    public void invalidate() {
        lock.lock();
        try {
            this.currentAccessToken = null;
            this.currentRefreshToken = null;
            this.tokenExpiresAt = null;
            log.info("Tokens invalidated");
        } finally {
            lock.unlock();
        }
    }
}
```

#### Service API CSWeb

```java
package com.example.service;

import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.*;
import org.springframework.stereotype.Service;
import org.springframework.web.client.RestTemplate;

@Slf4j
@Service
@RequiredArgsConstructor
public class CSWebApiService {

    private final CSWebAuthService authService;
    private final CSWebProperties cswebProperties;
    private final RestTemplate restTemplate;

    /**
     * GET request avec authentification
     */
    public <T> ResponseEntity<T> get(String endpoint, Class<T> responseType) {
        String url = cswebProperties.getUrl() + endpoint;
        HttpHeaders headers = createAuthHeaders();
        HttpEntity<?> request = new HttpEntity<>(headers);

        return restTemplate.exchange(url, HttpMethod.GET, request, responseType);
    }

    /**
     * POST request avec authentification
     */
    public <T> ResponseEntity<T> post(String endpoint, Object body, Class<T> responseType) {
        String url = cswebProperties.getUrl() + endpoint;
        HttpHeaders headers = createAuthHeaders();
        HttpEntity<?> request = new HttpEntity<>(body, headers);

        return restTemplate.exchange(url, HttpMethod.POST, request, responseType);
    }

    /**
     * Télécharger fichier
     */
    public byte[] downloadFile(String filename) {
        String endpoint = "/files/media/" + filename + "/content";
        String url = cswebProperties.getUrl() + endpoint;

        HttpHeaders headers = createAuthHeaders();
        headers.setAccept(List.of(MediaType.APPLICATION_OCTET_STREAM));

        HttpEntity<?> request = new HttpEntity<>(headers);

        ResponseEntity<byte[]> response = restTemplate.exchange(
            url,
            HttpMethod.GET,
            request,
            byte[].class
        );

        return response.getBody();
    }

    /**
     * Créer headers avec Authorization Bearer
     */
    private HttpHeaders createAuthHeaders() {
        String accessToken = authService.getAccessToken();

        HttpHeaders headers = new HttpHeaders();
        headers.set("Authorization", "Bearer " + accessToken);
        headers.setContentType(MediaType.APPLICATION_JSON);

        return headers;
    }
}
```

#### Exemple d'Utilisation

```java
package com.example.controller;

import com.example.service.CSWebApiService;
import lombok.RequiredArgsConstructor;
import org.springframework.web.bind.annotation.*;

@RestController
@RequestMapping("/api")
@RequiredArgsConstructor
public class DictionaryController {

    private final CSWebApiService cswebApiService;

    @GetMapping("/dictionaries")
    public String[] getDictionaries() {
        ResponseEntity<String[]> response = cswebApiService.get(
            "/dictionaries",
            String[].class
        );

        return response.getBody();
    }

    @GetMapping("/files/{filename}")
    public byte[] downloadFile(@PathVariable String filename) {
        return cswebApiService.downloadFile(filename);
    }
}
```

### Exemple 2 : Laravel (PHP)

#### .env

```bash
CSWEB_API_URL=http://localhost:8080/api
CSWEB_USERNAME=api_user
CSWEB_PASSWORD=SecurePassword123!
```

#### Service d'Authentification

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CSWebAuthService
{
    private string $apiUrl;
    private string $username;
    private string $password;

    public function __construct()
    {
        $this->apiUrl = config('services.csweb.api_url');
        $this->username = config('services.csweb.username');
        $this->password = config('services.csweb.password');
    }

    /**
     * Obtenir access token valide
     */
    public function getAccessToken(): string
    {
        // Vérifier cache
        $cachedToken = Cache::get('csweb_access_token');
        if ($cachedToken) {
            return $cachedToken;
        }

        // Authentifier
        return $this->authenticate();
    }

    /**
     * Authentification
     */
    private function authenticate(): string
    {
        $response = Http::asForm()->post("{$this->apiUrl}/token", [
            'grant_type' => 'password',
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if ($response->failed()) {
            Log::error('CSWeb authentication failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to authenticate with CSWeb');
        }

        $data = $response->json();

        // Sauvegarder tokens en cache
        $expiresIn = $data['expires_in'] ?? 3600;
        Cache::put('csweb_access_token', $data['access_token'], $expiresIn - 300);
        Cache::put('csweb_refresh_token', $data['refresh_token'], 30 * 24 * 3600);

        Log::info('CSWeb authentication successful');

        return $data['access_token'];
    }

    /**
     * Refresh token
     */
    public function refreshToken(): string
    {
        $refreshToken = Cache::get('csweb_refresh_token');

        if (!$refreshToken) {
            return $this->authenticate();
        }

        $response = Http::asForm()->post("{$this->apiUrl}/token", [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        if ($response->failed()) {
            Log::warning('CSWeb refresh failed, re-authenticating');
            return $this->authenticate();
        }

        $data = $response->json();

        // Mettre à jour cache
        $expiresIn = $data['expires_in'] ?? 3600;
        Cache::put('csweb_access_token', $data['access_token'], $expiresIn - 300);
        Cache::put('csweb_refresh_token', $data['refresh_token'], 30 * 24 * 3600);

        Log::info('CSWeb token refreshed');

        return $data['access_token'];
    }

    /**
     * Invalider tokens
     */
    public function invalidate(): void
    {
        Cache::forget('csweb_access_token');
        Cache::forget('csweb_refresh_token');
    }
}
```

#### Service API

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CSWebApiService
{
    private CSWebAuthService $authService;
    private string $apiUrl;

    public function __construct(CSWebAuthService $authService)
    {
        $this->authService = $authService;
        $this->apiUrl = config('services.csweb.api_url');
    }

    /**
     * GET request
     */
    public function get(string $endpoint): array
    {
        $token = $this->authService->getAccessToken();

        $response = Http::withToken($token)->get("{$this->apiUrl}{$endpoint}");

        return $response->json();
    }

    /**
     * Télécharger fichier
     */
    public function downloadFile(string $filename): string
    {
        $token = $this->authService->getAccessToken();
        $endpoint = "/files/media/{$filename}/content";

        $response = Http::withToken($token)->get("{$this->apiUrl}{$endpoint}");

        return $response->body();
    }
}
```

---

## Gestion des Erreurs

### Erreur 401 : Token Expiré

**Symptôme :**
```json
{
  "error": "invalid_token",
  "error_description": "The access token provided has expired"
}
```

**Solution :**
```java
// Interceptor RestTemplate (Spring Boot)
@Component
public class CSWebAuthInterceptor implements ClientHttpRequestInterceptor {

    @Override
    public ClientHttpResponse intercept(
        HttpRequest request,
        byte[] body,
        ClientHttpRequestExecution execution
    ) throws IOException {

        ClientHttpResponse response = execution.execute(request, body);

        // Si 401, refresh et retry
        if (response.getStatusCode() == HttpStatus.UNAUTHORIZED) {
            authService.forceRefresh();

            // Retry avec nouveau token
            String newToken = authService.getAccessToken();
            request.getHeaders().set("Authorization", "Bearer " + newToken);

            return execution.execute(request, body);
        }

        return response;
    }
}
```

### Erreur : Refresh Token Expiré

**Symptôme :**
```json
{
  "error": "invalid_grant",
  "error_description": "The refresh token is invalid"
}
```

**Solution :** Ré-authentifier avec username/password

```java
catch (Exception e) {
    log.warn("Refresh token expired, re-authenticating");
    currentRefreshToken = null;
    authenticate();
}
```

---

## Best Practices

### 1. Cacher les Tokens

✅ **Bon :**
```java
// Spring Boot - Singleton service avec cache mémoire
@Service
public class CSWebAuthService {
    private String currentAccessToken;  // Cache en mémoire
}
```

❌ **Mauvais :**
```java
// Ré-authentifier à chaque requête
public void callApi() {
    String token = authenticate();  // ❌ Trop de requêtes
    // ...
}
```

### 2. Refresh Proactif

✅ **Bon :**
```java
// Refresh 5 minutes AVANT expiration
Instant expiresAt = Instant.now().plusSeconds(expiresIn - 300);
```

❌ **Mauvais :**
```java
// Attendre 401 pour refresh
// ❌ Ralentit les requêtes
```

### 3. Thread-Safe

✅ **Bon :**
```java
private final ReentrantLock lock = new ReentrantLock();

public String getAccessToken() {
    lock.lock();
    try {
        // ...
    } finally {
        lock.unlock();
    }
}
```

### 4. Logs Sécurisés

✅ **Bon :**
```java
log.info("Authentication successful");
```

❌ **Mauvais :**
```java
log.info("Token: {}", accessToken);  // ❌ Token en logs !
```

---

## Exemples Complets

### Exemple : Synchroniser Cases

```java
@Service
@RequiredArgsConstructor
public class CaseSyncService {

    private final CSWebApiService cswebApi;

    @Scheduled(fixedRate = 300000)  // Toutes les 5 minutes
    public void syncCases() {
        log.info("Starting case synchronization");

        // 1. Récupérer dictionnaires
        ResponseEntity<Dictionary[]> dictionaries = cswebApi.get(
            "/dictionaries",
            Dictionary[].class
        );

        // 2. Pour chaque dictionnaire
        for (Dictionary dict : dictionaries.getBody()) {
            syncDictionaryCases(dict.getName());
        }

        log.info("Case synchronization completed");
    }

    private void syncDictionaryCases(String dictionaryName) {
        // 3. Récupérer cases
        String endpoint = "/dictionaries/" + dictionaryName + "/cases";
        ResponseEntity<Case[]> cases = cswebApi.get(endpoint, Case[].class);

        // 4. Sauvegarder en base locale
        for (Case caseData : cases.getBody()) {
            saveCase(caseData);
        }
    }
}
```

---

## Configuration Config Service

```java
// config/services.php (Laravel)
return [
    'csweb' => [
        'api_url' => env('CSWEB_API_URL'),
        'username' => env('CSWEB_USERNAME'),
        'password' => env('CSWEB_PASSWORD'),
    ],
];
```

---

**CSWeb Community Platform - OAuth2 Authentication Guide**

Made with ❤️ by Bouna DRAME
