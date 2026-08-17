# Contributing to CSWeb Community Platform

Thanks for considering a contribution! This document describes how the
project is versioned, branched and released. For coding conventions and
the local dev setup, see the [main README](../README.md) and the docs at
[bounadrame.github.io/csweb-community](https://bounadrame.github.io/csweb-community/).

---

## Versioning

The project follows [Semantic Versioning 2.0.0](https://semver.org/).

### Versions are aligned with upstream CSWeb

Every time the U.S. Census Bureau ships a new version of CSWeb on
[csprousers.org](https://csprousers.org), the Community fork follows
with a matching version:

| Upstream CSWeb | csweb-community |
| -------------- | --------------- |
| CSWeb 8.0.x    | v8.0.y          |
| CSWeb 8.1.x    | v8.1.y          |
| CSWeb 9.x      | v9.x.y          |

Patch (`y`) versions follow the **Community fork's own pace**. Census
8.0.5 does not force csweb-community 8.0.5 — the fork can already be on
8.0.7 by then because of features it added on top.

### What triggers a new maintenance line

A new long-lived maintenance branch is created when upstream ships a
release that is **not upgradeable in place** — regardless of whether it
is a major or a minor bump. The upstream upgrade script is the arbiter:
if `csweb/upgrade` refuses the jump, the two releases must coexist as
separate lines.

CSWeb 8.1 is the canonical example. It is only a minor bump, yet:

- The upstream upgrade script refuses to go from 8.0 to 8.1 — 8.1 must
  be installed as a fresh installation.
- CSPro 8.0 and earlier **cannot synchronize** with CSWeb 8.1, so
  operators must upgrade every field device in tandem.

Because field fleets cannot be re-flashed overnight, 8.0 and 8.1 are
maintained **in parallel**, each with its own branch, for as long as a
meaningful share of deployments still runs CSPro 8.0.

### Currently maintained lines

| Line     | Status  | Branch  | Upstream base |
| -------- | ------- | ------- | ------------- |
| v8.0.x   | Current | `8.x`   | CSWeb 8.0.x   |
| v8.1.x   | Beta    | `8.1.x` | CSWeb 8.1.x   |

`8.1.x` is a **fresh clone of upstream CSWeb 8.1** onto which the
Community feature set is re-applied — not a merge from `8.x`. Since the
two upstream trees are not upgrade-compatible, they are not
merge-compatible either.

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
master  ── next version in development
8.1.x   ── v8.1 line, based on upstream CSWeb 8.1 (Beta)
8.x     ── v8.0 line, based on upstream CSWeb 8.0 (Current)
```

### Where do I push my work?

| Type of change | Target branch |
| -------------- | ------------- |
| New feature, additive change | `master` |
| Bugfix on the current stable | `8.x` (then forward-port to `8.1.x`) |
| Security fix on the current stable | `8.x` (then forward-port to `8.1.x` and `master`) |
| Fix specific to the 8.1 port | `8.1.x` only |
| Breaking change | `master` only |

Because `8.x` and `8.1.x` sit on **different upstream trees**, a fix
that lands on `8.x` must be re-applied to `8.1.x` by cherry-pick, and
may need manual conflict resolution wherever upstream 8.1 changed the
surrounding code — the roles UI in particular.

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

## 📋 Processus de Contribution

### 1. Fork & Clone

```bash
# Fork le repo sur GitHub, puis :
git clone https://github.com/VOTRE-USERNAME/csweb-community.git
cd csweb-community
git remote add upstream https://github.com/BOUNADRAME/csweb-community.git
```

### 2. Créer une Branche

**❌ Ne jamais travailler directement sur `master` !**

```bash
# Créer une branche descriptive
git checkout -b feature/nom-de-votre-fonctionnalite

# Ou pour un bugfix
git checkout -b fix/description-du-bug
```

### 3. Développer & Tester

- Écrivez du code clair et documenté
- Testez vos changements localement avec Docker
- Suivez les conventions du projet (voir ci-dessous)

### 4. Commit

```bash
# Commits clairs et descriptifs
git add .
git commit -m "Type: Description concise du changement

Explication détaillée si nécessaire.
"
```

**Types de commits :**
- `Feat:` Nouvelle fonctionnalité
- `Fix:` Correction de bug
- `Docs:` Documentation
- `Refactor:` Refactoring sans changement fonctionnel
- `Test:` Ajout de tests
- `Chore:` Tâches de maintenance

### 5. Push & Pull Request

```bash
# Push vers votre fork
git push origin feature/nom-de-votre-fonctionnalite
```

Puis sur GitHub :
1. Allez sur votre fork
2. Cliquez sur **"Compare & pull request"**
3. Remplissez le template de PR
4. Attendez la review

---

---

## ✅ Checklist avant PR

- [ ] Mon code fonctionne localement avec Docker
- [ ] J'ai testé les fonctionnalités modifiées
- [ ] J'ai mis à jour la documentation si nécessaire
- [ ] Mes commits sont clairs et descriptifs
- [ ] Mon code respecte les conventions du projet
- [ ] J'ai résolu tous les conflits avec `master`

---

---

## 🛠️ Conventions du Projet

### PHP/Symfony
- PSR-12 code style
- Type hinting obligatoire
- PHPDoc pour les méthodes publiques

### Documentation (Nextra)
- Markdown avec front matter
- Langue : Français
- Ton : Positif et constructif

### Docker
- Tester avec `docker compose --profile local-postgres up -d`
- Vérifier les healthchecks

---

## 📞 Questions ?

- **Issues** : [GitHub Issues](https://github.com/BOUNADRAME/csweb-community/issues)
- **Discussions** : [GitHub Discussions](https://github.com/BOUNADRAME/csweb-community/discussions)

Merci de contribuer à CSWeb Community Platform ! 🚀

---

## Reporting security issues

Do **not** open a public GitHub issue for security findings. Email
`bounafode@gmail.com` with the details. Acknowledgement within 48 h,
fix or mitigation within 14 days for high-severity issues.

---

## License

By contributing you agree that your work is licensed under the
[Apache License 2.0](LICENSE) like the rest of the project.
