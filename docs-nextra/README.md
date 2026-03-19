# CSWeb Community Platform - Documentation Nextra

Documentation moderne et interactive construite avec [Nextra](https://nextra.site) (Next.js + MDX).

## 🚀 Quick Start

### Installation

```bash
cd docs-nextra
npm install
```

### Développement

```bash
npm run dev
```

Ouvrir http://localhost:3000

### Build Production

```bash
npm run build
npm start
```

---

## 📁 Structure

```
docs-nextra/
├── pages/                     # Pages MDX (auto-routing)
│   ├── index.mdx              # Landing page
│   ├── getting-started/       # 🚀 Getting Started (5 pages)
│   │   ├── index.mdx          # Bienvenue
│   │   ├── installation.mdx   # Installation 5 min
│   │   ├── first-breakout.mdx # Tutorial breakout
│   │   ├── verify.mdx         # Checklist
│   │   └── next-steps.mdx     # Prochaines étapes
│   ├── guides/                # 📚 Guides (20+ pages)
│   │   ├── breakout/          # Breakout sélectif, scheduler
│   │   ├── database/          # PostgreSQL, MySQL, SQL Server
│   │   ├── admin/             # Users, dictionaries, webhooks
│   │   ├── architecture/      # Flexible, security, performance
│   │   └── deployment/        # Docker, remote, backup
│   └── reference/             # 📖 Reference (15+ pages)
│       ├── api/               # OAuth, Breakout API, Webhooks
│       ├── cli/               # Commandes CLI
│       ├── config/            # .env, docker-compose
│       ├── troubleshooting/   # FAQ, Common Issues, Errors
│       └── resources/         # Changelog, Roadmap, Contributing
├── components/                # Composants React custom
│   ├── Callout.tsx            # Note/Warning/Error boxes
│   ├── Tabs.tsx               # Multi-language code examples
│   └── CodeBlock.tsx          # Code blocks avec copy button
├── public/                    # Assets statiques
│   └── images/                # Logos, screenshots
├── theme.config.jsx           # Configuration Nextra
├── next.config.js             # Configuration Next.js
└── package.json

Total: 40+ pages MDX
```

---

## ✨ Fonctionnalités

- ✅ **Recherche full-text** (FlexSearch)
- ✅ **Dark mode** (auto-switch)
- ✅ **Responsive** (mobile-first)
- ✅ **Copy code buttons** sur tous les snippets
- ✅ **Callouts** stylisés (Info, Warning, Error, Success)
- ✅ **Tabs** multi-langages (Java, PHP, Node.js)
- ✅ **Navigation automatique** (prev/next)
- ✅ **Table des matières** (TOC) flottante
- ✅ **Git timestamps** sur chaque page
- ✅ **Feedback widget** sur chaque page
- ✅ **i18n ready** (FR/EN)

---

## 🎨 Composants Custom

### Callout

```mdx
import { Callout } from '../components/Callout'

<Callout type="info">
  Ceci est une note informative.
</Callout>

<Callout type="warning">
  Attention : ceci est important !
</Callout>

<Callout type="error">
  CRITIQUE : Action requise immédiatement.
</Callout>

<Callout type="success">
  Succès : opération terminée !
</Callout>
```

### Tabs

```mdx
import { Tabs } from '../components/Tabs'

<Tabs items={['PostgreSQL', 'MySQL', 'SQL Server']}>
  <div>
    ```sql
    -- PostgreSQL query
    SELECT * FROM table;
    ```
  </div>
  <div>
    ```sql
    -- MySQL query
    SELECT * FROM table;
    ```
  </div>
  <div>
    ```sql
    -- SQL Server query
    SELECT * FROM table;
    ```
  </div>
</Tabs>
```

### CodeBlock

```mdx
import { CodeBlock } from '../components/CodeBlock'

<CodeBlock language="bash" filename=".env">
  DATABASE_URL=postgresql://user:pass@localhost:5432/db
</CodeBlock>
```

---

## 🚀 Déploiement

### Vercel (Recommandé)

1. **Connecter le repo à Vercel**
   - Aller sur https://vercel.com
   - Import Git Repository
   - Sélectionner `BOUNADRAME/pg_csweb8_latest_2026`

2. **Configuration Build**
   - Framework Preset: `Next.js`
   - Root Directory: `docs-nextra`
   - Build Command: `npm run build`
   - Output Directory: `.next`

3. **Custom Domain**
   - Ajouter `docs.csweb-community.org`

4. **Deploy**
   - Auto-deploy sur chaque push vers `master`
   - Preview deployments sur chaque PR

**URL Preview :** https://csweb-community-docs.vercel.app

---

## 📝 Conventions d'Écriture

### Frontmatter

```mdx
---
title: Titre de la Page
description: Description courte pour SEO (max 160 caractères)
---
```

### Structure Page

```mdx
---
title: Ma Page
description: Description
---

import { Callout } from '../components/Callout'

# Titre Principal

Introduction courte (1-2 paragraphes)

<Callout type="info">
  Note importante
</Callout>

---

## Section 1

Contenu...

### Sous-section 1.1

Contenu...

---

## Section 2

Contenu...
```

### Code Blocks

```mdx
\```bash
# Commandes bash
docker compose up -d
\```

\```sql
-- Requêtes SQL
SELECT * FROM table;
\```

\```json
{
  "config": "value"
}
\```
```

### Liens Internes

```mdx
[Texte du lien](/getting-started/installation)
[Anchor dans page](/getting-started/installation#section)
```

### Images

```mdx
![Description](../public/images/screenshot.png)

# OU avec HTML pour sizing
<img src="/images/logo.png" alt="Logo" width="200" />
```

---

## 🌍 Internationalisation (i18n)

### Ajouter une Langue

1. **Modifier `next.config.js`**
   ```js
   i18n: {
     locales: ['fr', 'en'],
     defaultLocale: 'fr',
   }
   ```

2. **Créer les fichiers traduits**
   ```
   pages/
   ├── index.mdx          (français)
   └── index.en.mdx       (anglais)
   ```

3. **Sélecteur de langue**
   Nextra ajoute automatiquement un sélecteur dans la navbar.

---

## 📦 Migration depuis Anciens Docs

### Mapping Fichiers

| Ancien Fichier | Nouvelle Page Nextra |
|----------------|---------------------|
| `QUICK-START.md` | `pages/getting-started/installation.mdx` |
| `GETTING-STARTED.md` | `pages/getting-started/index.mdx` |
| `ARCHITECTURE-FLEXIBLE.md` | `pages/guides/architecture/flexible.mdx` |
| `CONFIGURATION-MULTI-DATABASE.md` | `pages/guides/database/multi-db.mdx` |
| `MIGRATION-BREAKOUT-SELECTIF.md` | `pages/guides/breakout/selective.mdx` |
| `CSWEB-OAUTH-AUTHENTICATION.md` | `pages/reference/api/oauth.mdx` |
| `WEBHOOKS-INTEGRATION.md` | `pages/reference/api/webhooks.mdx` |
| `DOCKER-DEPLOYMENT.md` | `pages/guides/deployment/docker-prod.mdx` |

### Script de Migration

```bash
# TODO: Créer un script pour convertir Markdown → MDX
# - Ajouter frontmatter
# - Convertir callouts
# - Mettre à jour liens
```

---

## 🔧 Maintenance

### Rebuild Search Index

```bash
npm run build
```

Nextra rebuild automatiquement l'index de recherche à chaque build.

### Vérifier Liens Cassés

```bash
# Installer link-checker
npm install -g broken-link-checker

# Scanner le site
blc http://localhost:3000 -ro
```

### Optimiser Images

```bash
# Installer imagemin
npm install -g imagemin-cli imagemin-pngquant

# Optimiser
imagemin public/images/*.png --out-dir=public/images/optimized --plugin=pngquant
```

---

## 📊 Analytics

### Vercel Analytics

Automatiquement activé si déployé sur Vercel.

### Google Analytics

Ajouter dans `theme.config.jsx` :

```jsx
export default {
  // ...
  head: (
    <>
      <script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
      <script dangerouslySetInnerHTML={{
        __html: `
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', 'G-XXXXXXXXXX');
        `
      }} />
    </>
  )
}
```

---

## 🤝 Contributing

### Ajouter une Nouvelle Page

1. **Créer le fichier MDX**
   ```bash
   touch pages/guides/ma-nouvelle-page.mdx
   ```

2. **Ajouter le frontmatter**
   ```mdx
   ---
   title: Ma Nouvelle Page
   description: Description courte
   ---
   ```

3. **Ajouter dans `_meta.json`**
   ```json
   {
     "ma-nouvelle-page": "Ma Nouvelle Page"
   }
   ```

4. **Tester en local**
   ```bash
   npm run dev
   ```

### Guidelines

- ✅ Utiliser des exemples concrets (code, commandes, screenshots)
- ✅ Ajouter des Callouts pour les points importants
- ✅ Utiliser des Tabs pour multi-langages/SGBD
- ✅ Tester tous les code snippets
- ✅ Vérifier les liens internes
- ✅ Optimiser les images (< 200KB)
- ✅ Faire une PR avec description claire

---

## 📞 Support

- **GitHub Issues** : https://github.com/BOUNADRAME/pg_csweb8_latest_2026/issues
- **Discord** : https://discord.gg/csweb-community
- **Email** : docs@csweb-community.org

---

**Auteur :** Bouna DRAME
**License :** Apache 2.0
**Version :** 2.0.0
**Documentation :** 47 pages complètes ✅
