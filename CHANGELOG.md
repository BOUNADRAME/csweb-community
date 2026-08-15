# Changelog

All notable changes to **CSWeb Community Platform** are documented here.

This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [Semantic Versioning](https://semver.org/).

Versions are aligned with the upstream **CSWeb** distribution
maintained by the U.S. Census Bureau ([csprousers.org](https://csprousers.org)).
Patch versions follow the Community fork's own pace.

## Branching strategy

- `master` — next version in development
- `8.1.x` — v8.1 line, based on upstream CSWeb 8.1 (Beta)
- `8.x` — v8.0 line, based on upstream CSWeb 8.0 (Current)

`8.x` and `8.1.x` sit on **different upstream trees** and are not
upgrade-compatible: the upstream script refuses the 8.0 → 8.1 jump, and
CSPro 8.0 and earlier cannot synchronize with CSWeb 8.1. Both lines are
therefore maintained in parallel.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full workflow.

---

## [Unreleased]

_Nothing yet._

---

## [8.1.0-beta.1] - Unreleased (branch `8.1.x`)

First port of the Community layer onto the upstream CSWeb 8.1.2 base.
**Beta: not validated against a real CSPro 8.1 synchronization yet.**

### Changed

- Rebased on upstream CSWeb 8.1.2 (was CSWeb 8.0.x)
- Namespace `AppBundle\` → `App\`, source tree `src/AppBundle/` → `src/`,
  following the upstream 8.1 layout change
- Upstream now targets Symfony 6.4 (was 5.4), Monolog 3, DBAL ^3.9

### Fixed

- `src/version.php`: missing semicolon on the `CSPRO_VERSION` define
  (present in the upstream 8.1.2 release)

### Added

- `CSWEB_COMMUNITY_VERSION` constant, tracking the fork version
  independently of `CSPRO_VERSION`

### Added — Community permission layer

The upstream 8.1 permission model is adopted as-is rather than replaced.
The Community features plug into it through the standard pipeline, with
no upstream file modified:

- `CommunityPermissions`: permission catalogue claiming ids from 110 up,
  following the upstream convention of tens. Upstream owns 1..100, so a
  future upstream release can extend its range without colliding.
  - 110/111/112 `dashboard`, `dashboard.read`, `dashboard.write`
  - 120/121/122 `backup`, `backup.read`, `backup.write`
  - 130/131/132 `logs`, `logs.read`, `logs.write`
- `DashboardVoter`, `BackupVoter`, `LogsVoter`: same shape as the upstream
  voters, so `ApiKeyUserProvider::getUserRoles()` turns each permission
  row into `ROLE_<NAME>` unchanged
- `CommunitySchemaInstaller` + `csweb:community:install-schema`: idempotent
  installer tracked under `cspro_config.community_schema_version`,
  separate from the upstream `schema_version` counter. Three steps, which
  replace the 8.0 line's schema migrations 9 through 12:
  - v1 permissions and default role grants
  - v2 `cspro_dictionaries_schema`.`port` and `.db_type` (was schema 10/11)
  - v3 `cspro_backup_config` table, trigger and default row (was schema 9)

  Defers cleanly when the upstream tables do not exist yet, so it is safe
  on the very first container boot, and re-running it never re-applies a
  step or overwrites an operator's backup settings. `upgrade/upgrade.php`
  and `setup/configure.php` stay exactly as upstream ships them.
- Default grants: Administrator gets the full Community feature set;
  Developer (new in 8.1) gets dashboard and logs read access

### Changed

- Community controllers and templates now check the granular permissions
  (`BackupVoter::BACKUP_READ`, …) instead of the coarse `ROLE_*_ALL` names
  used on the 8.0 line, mapped read/write by HTTP method
- `base.twig`: Community nav entries appended in their own `navCommunity`
  block, leaving the upstream nav exactly as shipped
- `docker-entrypoint.sh`: replaced the hardcoded `dashboard_all` insert at
  id 11 with the installer command. **Id 11 means `apps.read` in the 8.1
  schema**, so the old statement would have silently corrupted permissions

### Added — Community settings in the admin UI

- Roles screen: `dashboard`, `backup` and `logs` rows, appended to the
  upstream `permissions` array in `dist/js/roles.js` — the whole table is
  generated from that array, so the rows, their checkboxes and the JSON
  payload all follow. `Role::fromJSON()/toJSON()` and the three
  `RolePermissions` maps gained the matching keys.
- Data configuration screen: **Database type** (PostgreSQL / MySQL /
  SQL Server) and **Port** fields on both the add and edit forms, with
  server-side validation. The backend already read `dbType` and
  `targetPort`; they were simply not reachable from the UI.

### Fixed

- `dist/js/roles.js`, `css/roles.css` and four other upstream assets were
  missing from the branch: `templates/roles.twig` references roles.js, so
  the roles screen would have been inert.
- `Role::addPermissionsFromJson()` read `$data[$key]` with no null guard,
  emitting a warning for a role saved before a key existed.
- The data-configuration modals collected `find("input")` only, so a
  `<select>` was never sent. Now `find("input, select")`.

### Ported

Community layer carried over from 8.0.x (17 PHP classes, 3 templates):
breakout status service, multi-database driver detection, breakout
database configuration and connection resolver, backup and scheduler
commands, configuration and driver check commands, dashboard, backup and
application-logs controllers with their templates, rate limiter and
security-headers subscribers, backup/breakout schedulers and error
formatter.

---

## [8.0.0] - 2026-05-06

First tagged release of the Community fork. Aggregates the work that
turned upstream CSWeb 8 into a Docker-friendly, multi-DB, ops-aware
deployment.

### Added

- **Breakout Health dashboard** at `/breakout/dashboard` — paginated
  per-dictionary status, KPI tiles, "Top problems" sidebar, optional
  Live mode (60 s refresh), Symfony cache backing (TTL 60 s, ~150x
  speedup over cold queries).
- New permission `ROLE_DASHBOARD_ALL` (id 11) plumbed end-to-end:
  `cspro_permissions` seed, `RolePermissions` constant, admin fallback,
  add/edit role UI, DataTable column, sidebar nav item.
- "Purge All" button on the Breakout Logs tab — wipes all log files in
  one click, scoped either globally or to the currently-selected
  dictionary.
- `BREAKOUT_CONNECTION_MODE` env switch (`direct` | `tunnel`) — `direct`
  passes host/port through unchanged, `tunnel` boots an `autossh` SSH
  forward at container startup so CSWeb can reach a database whose port
  is closed to the public internet.
- `BreakoutConnectionResolver` service — single source of truth that
  rewrites the effective host/port at connection time according to the
  active mode. Used by the dashboard service, the legacy breakout PDO
  path, and the standalone status webhook.
- Top-of-page banner on `/dataSettings` and inline reminders inside the
  Add / Edit Configuration modals when tunnel mode is active, so
  operators understand that hostname / port fields become cosmetic.
- Admin guide `docs-nextra/.../guides/admin/connection-modes.mdx` with
  a decision tree, full configuration matrices, and the migration
  playbook between modes.

### Changed

- **Webhook response shape unified** across the four root webhooks —
  every endpoint now returns `{success, data, error, meta?}` with a
  stable error-code catalogue (`missing_token`, `invalid_token`,
  `server_misconfigured`, `method_not_allowed`, `body_too_large`,
  `invalid_body`, `invalid_dictionary`, `invalid_filename`,
  `invalid_action`, `dictionary_not_found`, `file_not_found`,
  `file_not_readable`, `process_failed`, `breakout_failed`,
  `internal_error`, `rate_limited`).
- Pagination fields (`total`, `page`, `pages`, `limit`) move from the
  response root to a dedicated `meta` object.
- `dictionary-schema-webhook.php` register / unregister / status now
  accept `dictionary_name` in addition to `dictionary_id`, so external
  clients can reference dicts by symbolic name across environments.
- Sidebar item label "Dashboard" renamed to "Breakout Health" for
  clarity (avoids collision with the existing Data section).
- `composer.json` identity — package name, license, branch-alias updated
  to reflect the Community fork; no functional impact.
- `VERSION` file synchronised to `8.0.0`.

### Fixed

- `RoleController::addRole` was reading `settingsPermissions` (plural)
  while the JS posts `settingsPermission` (singular) — the Settings
  permission is now correctly persisted on role creation.
- `tablePrefix()` in the dashboard service used to strip `_DICT`
  anywhere in the name (`MY_DICTIONARY` became `MYIONARY`); now it
  strips only the trailing suffix.
- "Run now" from the dashboard sends `id=0`; we no longer create a
  phantom schedule row in `cspro_breakout_scheduler`.
- AbortController cancels in-flight summary / list fetches when the
  user paginates / filters / refreshes in quick succession, eliminating
  out-of-order rendering.
- `dictionary-schema-webhook.php::registerSchema` now wraps
  `schema_password` in `AES_ENCRYPT(?, 'cspro')` on insert / update,
  matching the read path used by the legacy breakout code.

### Security

- Removed the public, hardcoded fallback token (`kairos_breakout_2024`)
  from three of the four root webhooks. `BREAKOUT_WEBHOOK_TOKEN` is now
  mandatory; missing values yield a fail-fast `500 server_misconfigured`.
- 60 req / 60 s sliding-window rate limit applied uniformly to the four
  webhooks via the shared helper. 429 responses include a `Retry-After`
  header.
- POST bodies capped at 64 KB across the webhooks (413 `body_too_large`
  when exceeded) to bound JSON parser cost.
- `log-reader-webhook.php` now applies a strict allowlist
  (`^[a-zA-Z0-9._-]+\.log$`) plus a `realpath` containment check;
  blocks dotfiles, symlink escapes and any non-`.log` extension. Listing
  endpoint filters identically.
- `dictionary-schema-webhook.php::registerSchema` validates `host_name`
  against an RFC 952/1123 hostname or IP regex and rejects IPv4
  link-local `169.254/16` (cloud metadata SSRF surface).
- Stack traces no longer leak through error responses on the dashboard
  controller and the run-now scheduler endpoint; full details land in
  the server-side log only.
- `BreakoutStatusService` rejects dictionary names that don't match
  `^[A-Z0-9_]{1,64}$` before interpolating them into source / target
  table identifiers.
- `DataSettingsController::runNowSchedule` validates `dictName` with the
  same regex before constructing the breakout log file path.
- New `X-Robots-Tag: noindex, nofollow` emitted on every webhook
  response.
- New env var `BREAKOUT_WEBHOOK_VERBOSE` (default off): when off,
  `breakout-webhook.php` truncates `output` / `stderr` to the last 4 KB
  to limit log exfiltration via the API. The full content is always
  available in the persisted `data.logFile`.

### Removed

- The fallback default for `BREAKOUT_WEBHOOK_TOKEN` (was the public
  string `kairos_breakout_2024`). All four webhooks now require the
  variable to be set.

### Performance

- Symfony cache pool wraps `getStatusList`, `getGlobalSummary` and
  `getTopProblems` (TTL 30–60 s). Manual Refresh button passes
  `?nocache=1` to bypass the cache on demand.
- Per-request memoisation of source / target row counts — `COUNT(*)`
  is computed at most once per dict per HTTP request, even across the
  dashboard summary and the paginated list calls.
- Pool of PDO target connections keyed on
  `host|port|db_type|schema` — N dictionaries pointing to the same
  target schema reuse a single connection.
- `INFORMATION_SCHEMA.TABLES.TABLE_ROWS` fallback when a source
  dictionary table exceeds 5 000 000 rows, avoiding an expensive
  `COUNT(*)` for KPI computation.

### Migration notes (upgrading from any pre-8.0.0 build)

1. Set `BREAKOUT_WEBHOOK_TOKEN` in `.env` (e.g. `openssl rand -hex 32`).
   Without it, the four root webhooks return `500 server_misconfigured`
   at startup.
2. The `cspro_permissions` table gains row id 11
   (`name='dashboard_all'`). The Docker entrypoint inserts it
   idempotently at boot.
3. External clients of the four webhooks must adapt to the unified
   response shape (`{success, data, error, meta?}`) and to the stable
   error-code catalogue. Prior format with top-level fields and
   string `error` is gone.
4. Existing user sessions do **not** automatically gain
   `ROLE_DASHBOARD_ALL` — log out / log back in to refresh the cached
   roles. Admins receive it via the hardcoded fallback in
   `ApiKeyUserProvider`.

[Unreleased]: https://github.com/BOUNADRAME/csweb-community/compare/v8.0.0...HEAD
[8.0.0]: https://github.com/BOUNADRAME/csweb-community/releases/tag/v8.0.0
