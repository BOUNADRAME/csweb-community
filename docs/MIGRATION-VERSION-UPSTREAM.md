# Porter la couche Community sur une nouvelle version de CSWeb

Guide de référence, écrit à partir du portage réel de **CSWeb 8.0.x → 8.1.2**
(branche `8.1.x`, 24 commits, 238 fichiers). Il vaut pour les versions
suivantes : 8.2, 9.0, etc.

Il documente **la méthode**, **l'inventaire de ce qu'il faut porter**, et
surtout **les 15 pièges rencontrés** — c'est cette dernière partie qui fait
gagner le plus de temps.

---

## 1. Décider : nouvelle ligne ou montée de version ?

Le critère n'est pas « majeur ou mineur », c'est **l'upgradabilité amont**.

> Le script `csweb/upgrade` refuse-t-il le passage ?

CSWeb 8.1 est un *mineur* et pourtant :

- l'upgrade 8.0 → 8.1 est refusé par l'amont (installation neuve obligatoire) ;
- **CSPro 8.0 et antérieur ne peuvent pas synchroniser avec CSWeb 8.1**.

La seconde contrainte est la plus lourde : monter le serveur oblige à mettre à
jour **tout le parc de tablettes**. Sur une enquête en cours, c'est
irréalisable — d'où deux lignes maintenues en parallèle.

| Cas | Conséquence |
|---|---|
| Upgrade amont accepté | Montée de version sur la ligne existante |
| Upgrade amont refusé | **Nouvelle branche `X.Y.x`**, clone frais, les deux lignes coexistent |

Voir `.github/CONTRIBUTING.md` pour la politique de branches.

---

## 2. Le principe directeur

> **Ne rien dénaturer du vanilla. Rendre dynamique ce qui est figé.**

L'amont code souvent un cas particulier en dur. La couche Community le
généralise, sans changer le comportement par défaut :

| Le vanilla fige | La couche rend dynamique |
|---|---|
| `'driver' => 'pdo_mysql'` | `resolveDriver($dbType)` → PG / MySQL / SQL Server |
| Backticks MySQL en dur | `qi()` → `quoteIdentifier()` de la plateforme |
| Tables `cases`, `notes` | `<dict>_cases` → plusieurs dictionnaires par schéma |
| `LIMIT 1` | `modifyLimitQuery()` → syntaxe par plateforme |
| Hôte/port bruts | `BreakoutConnectionResolver` → tunnel SSH transparent |
| `catch (\Exception)`, exit 0 | `catch (\Throwable)`, statut FAILED persisté |

**Test de non-régression :** sur un déploiement MySQL mono-dictionnaire, le
comportement doit être *identique* au vanilla.

### Trois règles qui découlent du principe

1. **Ne jamais éditer `setup/configure.php` ni `upgrade/upgrade.php`** pour y
   ajouter du schéma Community. Utiliser `CommunitySchemaInstaller`.
2. **Ne jamais toucher `cspro_config.schema_version`** — il appartient à
   l'amont. La couche a son propre `community_schema_version`.
3. **Passer par les points d'extension existants** : `App\: resource: src/*`
   autoenregistre les services, `ApiKeyUserProvider` transforme toute
   permission en `ROLE_<NOM>`, les blocs Twig `{% block %}` se surchargent.

---

## 3. Méthode de portage — les lots

L'ordre compte : chaque lot rend le suivant testable.

### Lot 0 — Socle

```bash
git checkout -b 8.2.x master
curl -o /tmp/csweb.zip https://csprousers.org/releases/8.2/csweb-8.2.0.zip
unzip /tmp/csweb.zip -d /tmp/csweb-8.2.0/
```

Sauvegarder la couche Community hors du dépôt, remplacer `src/`, `templates/`,
`app/`, `setup/`, `upgrade/`, `api/`, `bin/`, `composer.json` par l'amont,
puis réinjecter la couche.

**Conserver l'infrastructure du fork** : `docker-compose.yml`,
`docker-entrypoint.sh`, `Dockerfile`, `docker/`, les webhooks, `tests/`,
`docs/`, `.github/`.

⚠️ **Ne pas oublier `dist/`, `css/`, `js/`** — oubli n° 3 ci-dessous.

### Lot 1 — Ruptures structurelles

Repérer ce que l'amont a changé sans le documenter :

```bash
diff <(git show master:composer.json) <(cat /tmp/csweb-8.2.0/composer.json)
ls /tmp/csweb-8.2.0/src/        # arborescence changée ?
```

En 8.1 : namespace `AppBundle\` → `App\`, `src/AppBundle/` → `src/`, Symfony
5.4 → 6.4, Monolog 2 → 3.

### Lot 2 — Fichiers additifs

Les fichiers 100 % Community, sans équivalent amont : ils se transposent tels
quels après renommage de namespace.

### Lot 3 — Permissions

Vérifier si l'amont a changé son modèle. En 8.1 il est passé de 10 permissions
grossières à 34 à grain fin, avec un rôle **Developer**.

### Lot 4 — Fichiers patchés

Le gros du travail. Pour chaque fichier amont que le fork modifiait :
comparer, et **ré-implémenter** la fonctionnalité sur la nouvelle base plutôt
que rejouer un diff — l'amont refactorise.

### Lot 5 — Schéma

Rebaser les migrations Community sur `CommunitySchemaInstaller`.

### Lot 6 — Validation

`docker compose up`, setup, puis **synchronisation depuis un CSPro réel**.
Tant que ce dernier test n'est pas passé, la version reste en `beta` dans
`docs-nextra/versions.json`.

---

## 4. Inventaire de la couche Community

À réévaluer à chaque portage — l'amont absorbe parfois une fonctionnalité du
fork, qui devient alors inutile.

### Fichiers 100 % Community (~20)

| Domaine | Fichiers |
|---|---|
| Services | `BreakoutStatusService`, `BreakoutDatabaseConfig`, `DatabaseDriverDetector`, `BreakoutConnectionResolver` |
| Commandes | `BackupRunCommand`, `BackupCleanupCommand`, `CheckConfigCommand`, `CheckDatabaseDriversCommand`, `SchedulerRunCommand`, `CommunityInstallSchemaCommand` |
| Contrôleurs | `DashboardController`, `BackupController`, `ApplicationLogsController` |
| Métier | `BackupScheduler`, `BreakoutScheduler`, `BreakoutErrorFormatter` |
| Sécurité | `RateLimiterSubscriber`, `SecurityHeadersSubscriber`, `DashboardVoter`, `BackupVoter`, `LogsVoter` |
| Schéma | `CommunityPermissions`, `CommunitySchemaInstaller` |
| Vues | `dashboard.twig`, `backup.twig`, `applicationLogs.twig` |

### Fichiers amont patchés (~22)

`DataSettings`, `MySQLQuestionnaireSerializer`, `MySQLDictionarySchemaGenerator`,
`DictionarySchemaHelper`, `MapDataRepository`, `BlobBreakOutWorker`,
`RolesRepository`, `Role`, `RolePermissions`, `DataSettingsController`,
`RoleController`, `ApiKeyUserProvider`, `HttpHelper`, plus
`base.twig`, `dataSettings.twig`, `roles.twig`.

### Devenus obsolètes en 8.1

Utile de le vérifier : cela réduit le travail.

| Patch 8.0 | Pourquoi obsolète |
|---|---|
| `ROLE_*_ALL` en dur dans `ApiKeyUserProvider` | Le modèle fin les génère depuis la base |
| IDs 9/10/11 dans `Role.php` | **Dangereux** : en 8.1 ce sont `apps`/`apps.read`/`apps.write` |
| `addError()` → `error()` | Monolog 3 l'impose déjà |
| `setContainer(?Container)` | Déjà dans le vanilla 8.1 |
| `VectorClock` | Correctif absorbé par l'amont |

---

## 5. Les 15 pièges rencontrés

Chacun a coûté du temps. Les vérifier explicitement au prochain portage.

### Build et image

**1. `composer.lock` désynchronisé.** Prendre le `composer.json` amont en
gardant l'ancien lock → build cassé (`v5.4.48 ne satisfait pas ^6.4`).
→ Repartir du lock amont, puis `composer require` les dépendances du fork.
Attention aux contraintes de plateforme : `cron-expression` v3.6 exige PHP ≥ 8.2.

**2. Répertoires requis manquants.** 8.1 a élargi la vérification de
`setup/prereqs.php` : `var`, `files`, **`files_csweb`**, `app/config`, `src/`.
`files_csweb` arrive **vide** dans l'archive → git ne le transporte pas.
→ `mkdir` dans le `Dockerfile` + `.gitkeep`.

**3. `bower_components` incompatibles.** 8.1 livre ses assets **pré-construits**
dans l'archive, à plat (`bootstrap/css/`), alors que `bower install` produit
l'arborescence de Bootstrap 3 (`bootstrap/dist/css/`, pas de `fontawesome-free`).
Chaque CSS tombait en 404 → redirection relative vers `./setup/` →
`ERR_TOO_MANY_REDIRECTS`.
→ Extraire `bower_components` de l'archive amont au build.

**4. Répertoires oubliés au clone.** `dist/js/roles.js` et `css/roles.css`
n'avaient pas été copiés : l'écran des rôles rendait une table vide.
→ Comparer l'archive et la branche : `comm -23 <(cd archive && find …) <(find …)`

### Configuration et démarrage

**5. Service non câblé.** `RateLimiterSubscriber` prend un `string $cacheDir`
qu'autowiring ne peut pas résoudre. La déclaration perdue → **toute
l'application en HTTP 500**.
→ Comparer la liste des services de l'ancien `services.yml` et du nouveau.
À déclarer dans **les deux noyaux** (UI et API).

**6. `PHP_BINARY` vide sous Apache.** Vaut le chemin CLI **uniquement** sous le
SAPI CLI. Sous mod_php il est vide → `sh: 1: : Permission denied`, échec
silencieux de l'installateur.
→ Résoudre explicitement avec repli sur `/usr/local/bin/php`.

**7. `config.php` perdu au rebuild.** L'entrypoint le persiste au **démarrage**,
mais le setup l'écrit **pendant** l'exécution : la copie n'avait jamais lieu.
→ Persister depuis `setup/complete.php`.

**8. Installateur jamais déclenché.** Même angle mort : au premier boot
`config.php` n'existe pas, donc l'entrypoint saute l'installateur, et rien ne le
relance après le setup.
→ Appeler depuis `setup/complete.php` ; l'entrypoint reste le second filet.

### URL et réseau

**9. URL API publique vs interne.** `API_URL` servait à deux usages
incompatibles : la spec de synchronisation `SyncService=` lue par **les
tablettes** (adresse publique, avec port publié), et le `baseUri` de
`HttpHelper` résolu **dans le conteneur** (port 80).
→ Deux paramètres : `cspro_rest_api_url` (public, déduit de la requête) et
`cspro_internal_api_url` (`CSWEB_INTERNAL_API_URL`).

**10. Hôte de base de données.** `mysql` (nom du service Docker) et non
`localhost`, qui désignerait le conteneur CSWeb lui-même.

### Schéma et base

**11. `schema_version` corrompu.** `BackupScheduler` le poussait à 9 — correct
sur la ligne 8.0 où le compteur amont portait aussi les migrations Community.
Sur 8.1 l'amont est à 8, et `CSProSchemaValidator` **rejette toute l'API** :

> The database schema version does not match the version of the CSWeb code.

Symptôme trompeur : la table Users paraissait vide, alors que les données
viennent de `/api/users`.
→ Ne jamais écrire `schema_version`. Réparation d'une base existante :
`UPDATE cspro_config SET value=8 WHERE name='schema_version';`

**12. Colonne renommée.** `cspro_dictionaries.dictionary_name` → **`name`**.
Trois fichiers Community l'utilisaient encore, dont `CSWebProcessRunnerByDict`
— tout breakout aurait échoué.
→ `grep -rn "dictionary_name" src/`

**13. Tables créées paresseusement.** `cspro_breakout_scheduler` était créée par
`setup/configure.php` sur la ligne 8.0 — fichier désormais vanilla, donc plus
personne ne la créait.
→ Toutes les tables Community dans `CommunitySchemaInstaller`.

### Framework

**14. Symfony 6.4 : `InputBag::get()` sur un tableau.** 5.4 retournait le
tableau, 6.4 **lève une exception**. DataTables envoie `search` et `order` sous
forme de tableaux → requête interrompue, loader infini.
→ Utiliser `all()`.

**15. Portabilité SQL.** `LIMIT` est une erreur de syntaxe sur SQL Server, et
un `INSERT` y est plafonné à **2100 paramètres**.
→ `modifyLimitQuery()` et découpage par lots. Attention : distinguer les
requêtes visant la base **source** (toujours MySQL via `PdoHelper`) de celles
visant la base **cible** (multi-moteur).

---

## 6. Commandes de diagnostic utiles

```bash
# Fichiers de l'archive amont absents de la branche
comm -23 <(cd /tmp/csweb-X.Y && find . -type f | sort) <(find . -type f | sort)

# Routes perdues au portage
comm -23 <(git show master:… | grep -oE "#\[Route\('[^']*'") <(grep -oE …)

# Toutes les routes enregistrées
docker compose exec csweb php bin/console debug:router --env=prod

# Erreurs applicatives
docker compose exec csweb tail -c 4000 /var/www/html/var/logs/ui.log \
  | grep -oE '"context":"[^"]{0,300}'

# Syntaxe de tous les fichiers PHP
for f in $(find src/ -name "*.php"); do php -l "$f" >/dev/null || echo "KO $f"; done

# État du schéma
docker compose exec mysql mysql -uroot -p"$MYSQL_ROOT_PASSWORD" csweb_metadata \
  -e "SELECT name,value FROM cspro_config WHERE name LIKE '%version%';"
```

---

## 7. Checklist de validation

Avant de passer une ligne de `beta` à `current` :

- [ ] Toutes les routes UI et API de la ligne précédente répondent
- [ ] `php -l` passe sur tous les fichiers de `src/`
- [ ] Blocs Twig équilibrés sur les templates modifiés
- [ ] Setup sur base vierge : toutes les tables créées en une passe
- [ ] `community_schema_version` à jour, `schema_version` **inchangé**
- [ ] Permissions Community accordées au rôle Administrator
- [ ] Menu latéral complet (le rôle admin n'est pas éditable après coup)
- [ ] `config.php` persisté et survivant à un rebuild
- [ ] Breakout réel sur **MySQL**, **PostgreSQL** et **SQL Server**
- [ ] Tables cibles préfixées `<dict>_`
- [ ] Compteur « Processed Cases » cohérent
- [ ] Échec provoqué → statut FAILED + message lisible
- [ ] **Synchronisation depuis un CSPro réel** ← le juge de paix

---

## 8. Points ouverts au 2026-08-16

À reprendre, indépendamment du portage :

| Sujet | État |
|---|---|
| SQL Server | Code porté, **jamais testé contre une instance réelle** |
| Montée en charge | Inconnue : aucun test au-delà de quelques cas |
| Injection SQL | `deleteQuestionnaires()` et `getCaseIdsMap()` interpolent des case-id sans bind (préexistant) |
| Tunnel SSH | Global, pas par dictionnaire : une seule cible à la fois en mode `tunnel` |
| Lien mort amont | `users.twig` et `syncReport.twig` référencent `datatables/media/css/…`, absent de l'archive 8.1.2 |
| Healthchecks | `csweb-app` teste `/api/` qui n'a pas de route → toujours `unhealthy` |
