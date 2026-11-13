# Changelog

All notable changes to **CSWeb Community Platform** are documented here.

This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [Semantic Versioning](https://semver.org/).

Major versions are aligned with the upstream **CSWeb** distribution
maintained by the U.S. Census Bureau ([csprousers.org](https://csprousers.org)).
Minor and patch versions follow the Community fork's own pace.

## Branching strategy

- `master` — next major in development (currently future v9)
- `8.x` — v8 maintenance branch, security and bugfix backports

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full workflow.

---

## [Unreleased]

_Nothing yet._

---

## [8.0.5] - 2026-11-13 (branch `8.0.x`)

Breakout Health always answers, whatever the state of the breakout targets.

### Fixed

- **Breakout Health stayed on "Loading…" for 30 s or more** when a breakout
  target database did not answer (firewall, host down). The connect attempt
  is now capped at 3 s, an unreachable target is remembered for 60 s so the
  next loads answer at once, and each dictionary shows why its target cannot
  be reached (`target_status`, `target_error`).
- **Deleting a breakout configuration hung** on an unreachable target before
  removing it, and the page showed nothing on failure. Target connections are
  now capped at 10 s, the page says when the target tables were left in place,
  and any delete error is shown on screen.
- **Global completion showed 0 %** when a target was unreachable or some
  dictionaries were not configured. It is now measured on the dictionaries
  whose target answered.
- A failed dashboard request now shows an error with a **Retry** button
  instead of an endless "Loading…".

### Added

- Breakout Health: "Target unreachable" status filter (server side), side
  card and `dicts_unreachable` counter on the Cases processed tile.

No database or `.env` change.

---

## [8.0.4] - 2026-11-07 (branch `8.0.x`)

### Security
- **Database ports listen on 127.0.0.1 by default.** The MySQL metadata
  database, the local breakout databases (PostgreSQL, MySQL, SQL Server)
  and phpMyAdmin / pgAdmin were published on 0.0.0.0, and Docker bypasses
  the host firewall (UFW, firewalld): on a VM with a public IP they were
  reachable from the Internet. CSWeb reaches them over the Docker network,
  so nothing changes for the application. Set `DB_BIND_ADDRESS` or
  `ADMIN_TOOLS_BIND_ADDRESS` to `0.0.0.0` to expose them on purpose;
  an SSH tunnel is the safer way to reach them from another machine.

### Changed
- Docker build: skip pdo, mbstring and xml, which the PHP image already
  has, compile extensions on every CPU, and build pdo_sqlsrv only.

---

## [8.0.3] - 2026-11-07 (branch `8.0.x`)

### Security
- **CSRF protection on the admin screens.** The admin UI is
  authenticated by a cookie, which a browser also sends on a request
  forged by another site, and no CSRF token was checked: a page visited by
  a logged-in administrator could change or delete configurations,
  schedules, backups, users or roles. Every POST, PUT, DELETE and PATCH to
  the admin controllers now needs the page's CSRF token (X-CSRF-TOKEN),
  as on 8.1.x; requests without it get HTTP 403. API calls are not
  affected (they use an Authorization header).

### Changed
- Backup and Application Logs: confirmation dialogs translated from
  French to English, in line with the rest of the interface.
- `.env.example`: performance guidance from 8.1.x (apply with
  `docker compose up -d`, never `restart`; check with
  `csweb:check-config`; reference values) and a link to the sizing guide,
  replacing a reference to a file that does not exist.

---

## [8.0.2] - 2026-11-07 (branch `8.0.x`)

Admin screens brought to the 8.1.x level: simpler, consistent, readable.

### Added
- **Test connection** in Add and Edit Configuration: checks the engine,
  host, port, database and credentials before saving, and says why it
  fails (wrong password, unknown database, nothing listening on the port).
- Settings > Data opens the tab named in the URL (`#scheduler`, `#logs`,
  `#applog`), so a reload or a shared link keeps the same tab.
- A selective breakout run now ends with a one-line summary per
  dictionary (jobs processed and duration, or "already up to date").

### Changed
- Settings > Data: a DB Type badge (PostgreSQL, MySQL, SQL Server), the
  target port next to the host, and compact outline Edit / Delete buttons.
- Dates on the admin screens (Data, Scheduler, Backup, Application Logs)
  are shown in the browser's locale, e.g. "Nov 07, 2026, 10:38 AM", and
  still sort correctly.
- Row actions are single-line outline buttons with tooltips on the
  Scheduler, Breakout Logs and Backup screens.
- Breakout Health: search and status filters run on the server over every
  page, and the KPI cards follow the filter, with a **Filtered** badge.
- Backup: clickable cron shortcuts in English (same pattern as the
  Breakout Scheduler) instead of French help text, and a Help link.
- Shorter port placeholder in the configuration dialogs.

---

## [8.0.1] - 2026-11-07 (branch `8.0.x`)

Large-survey and census readiness for the 8.0 line (CSPro 8.0). Brings
over the 8.1.x fixes that do not depend on the CSWeb version.

### Fixed
- **Rate limiting blocked field teams sharing one public IP.** Logins
  were limited to 5 per minute per IP and API calls to 60 per minute per
  IP and route, shared by every tablet behind a carrier NAT or a training
  centre's Wi-Fi. Logins are now counted per account (5/min) under a
  per-IP ceiling (60/min), API calls per session (600/min), and all four
  limits are configurable from `.env` (`RATE_LIMIT_LOGIN_PER_USER`,
  `RATE_LIMIT_LOGIN_PER_IP`, `RATE_LIMIT_API_PER_MIN`,
  `RATE_LIMIT_UI_PER_MIN`). Load test, 40 enumerators with one account
  each: 3 cases/s and 36 of 40 logins refused before, 172 cases/s and no
  error after.
- **Breakout job claim.** Several workers per dictionary could take the
  same job or create two jobs over the same case range. The claim now
  runs under an exclusive lock per target and dictionary.
- **Breakout to MySQL and SQL Server targets**: identifiers are quoted
  by the target platform instead of PostgreSQL-style double quotes
  (SQLSTATE 1064 on MySQL).
- **Wide questionnaires on MySQL**: breakout tables use the DYNAMIC row
  format, avoiding InnoDB's 8126-byte row limit.
- **Breakout failures are visible**: a failing job is marked FAILED with
  a readable error message in the target `_cspro_jobs` table and the UI,
  and is retried on the next run.
- **Run now** records the exit code, file size and log of a schedule or
  a backup instead of staying "in progress".
- **Breakout Health** and the status webhook count breakouts started
  from the CLI or a webhook.
- **Sync and Map Report** read the prefixed breakout tables.
- **Processed Cases 0** on case-sensitive MySQL in Data Configuration.
- **https behind a reverse proxy** (trusted proxy headers).
- **Backups survive `docker compose up -d`** (new `csweb_backups` volume).
- `MYSQL_HOST_PORT` decoupled from the internal MySQL port.

### Added
- Sync load-test tool `tools/loadtest/csweb_sync_load.py` (Python 3, no
  dependency), compatible with the 8.0 and 8.1 case formats.
- CI runs on the `8.0.x` maintenance branch.

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
