# Contributing to CSWeb Community Platform

Thanks for considering a contribution! This document describes how the
project is versioned, branched and released. For coding conventions and
the local dev setup, see the [main README](README.md) and the docs at
[bounadrame.github.io/csweb-community](https://bounadrame.github.io/csweb-community/).

---

## Versioning

The project follows [Semantic Versioning 2.0.0](https://semver.org/).

### Major versions are aligned with upstream CSWeb

Every time the U.S. Census Bureau ships a new major of CSWeb on
[csprousers.org](https://csprousers.org), the Community fork follows
with a matching major:

| Upstream CSWeb | csweb-community |
| -------------- | --------------- |
| CSWeb 8.x      | v8.x.y          |
| CSWeb 9.x      | v9.x.y          |
| CSWeb 10.x     | v10.x.y         |

Minor (`y`) and patch (`z`) versions follow the **Community fork's own
pace**. Census 8.0.5 does not force csweb-community 8.0.5 — the fork
can already be on 8.3.7 by then because of features it added on top.

### What constitutes a breaking change (and bumps the major)

- A change in the public webhook response shape (e.g. moving a field).
- A change in the URL or query-string contract of a webhook or UI route
  used by external clients.
- An env var becoming **mandatory** when it used to be optional.
- A SQL schema change that requires manual operator intervention to
  migrate (idempotent boot-time `INSERT IGNORE` does not count).
- Removing a permission, role, or dropping support for a database driver.

Anything else (additive features, idempotent migrations, internal
refactors) is **minor** or **patch**.

---

## Branching model

Inspired by the Next.js / Symfony model:

```
master  ── next major in development (currently leading to v9)
8.x     ── v8 maintenance branch (security + bugfixes backports)
7.x     ── (would exist if a CSWeb 7 fork had been released)
```

### Where do I push my work?

| Type of change | Target branch |
| -------------- | ------------- |
| New feature, additive change | `master` (next major) |
| Bugfix on the current stable | `8.x` |
| Security fix on the current stable | `8.x` (then forward-port to `master`) |
| Breaking change | `master` only |

If you are unsure, open a PR against `master` and we'll re-target during
review.

### Backporting workflow

When a fix lands on `master` and applies to `8.x`:

```bash
git checkout 8.x
git cherry-pick <commit-from-master>
# Resolve conflicts if needed
git push origin 8.x
```

The maintainer then publishes a patch tag (e.g. `v8.0.1`) and a Docker
image tagged `csweb-community:8.0.1` plus the rolling tag
`csweb-community:8`.

### Why this model?

- **Predictable for users**: a deployment pinned to `csweb-community:8`
  receives only non-breaking improvements until they explicitly upgrade
  to v9.
- **Free hand on master**: experimentation toward the next major never
  destabilises production users.
- **Aligned with the upstream cadence**: when Census ships CSWeb 9,
  master converges into v9.0.0 and the fresh `9.x` branch is created.

---

## Release checklist

When tagging a new version (maintainer-only):

1. Update `VERSION` to the new version number.
2. Update `CHANGELOG.md` — move entries from `[Unreleased]` to a new
   `[X.Y.Z] - <date>` section. Add SemVer comparison links at the
   bottom.
3. Commit: `Chore: Release vX.Y.Z`.
4. Tag: `git tag -a vX.Y.Z -m "csweb-community vX.Y.Z"`.
5. Push: `git push origin <branch> && git push origin vX.Y.Z`.
6. Build and push the Docker image (CI handles this once the GitHub
   Actions workflow is in place):
   ```
   docker buildx build --platform linux/amd64,linux/arm64 \
     -t bounadrame/csweb-community:X.Y.Z \
     -t bounadrame/csweb-community:X \
     --push .
   ```
7. Draft a GitHub Release pointing at the tag, with the CHANGELOG
   excerpt as the body.

---

## Pull request guidelines

1. One logical change per PR. If your branch contains "feat X + fix Y +
   refactor Z", split it.
2. PR title follows the existing commit-message convention:
   `Feat:`, `Fix:`, `Refactor:`, `Docs:`, `Chore:`, `Style:`,
   `Security:`.
3. Update `CHANGELOG.md` under `[Unreleased]` in the same PR.
4. If your change touches the public webhook contract, the env-var
   surface, or the database schema, **call it out explicitly** in the
   PR description so it is reviewed as a potential major-bump trigger.
5. Local sanity before pushing:
   ```
   composer install --no-dev
   php bin/console lint:twig templates/
   php -l <changed PHP files>
   ```

---

## Reporting security issues

Do **not** open a public GitHub issue for security findings. Email
`bounafode@gmail.com` with the details. Acknowledgement within 48 h,
fix or mitigation within 14 days for high-severity issues.

---

## License

By contributing you agree that your work is licensed under the
[Apache License 2.0](LICENSE) like the rest of the project.
