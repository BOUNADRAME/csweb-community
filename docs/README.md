# Documentation technique

Notes de référence détaillées, destinées aux **mainteneurs et administrateurs**.

Pour la documentation **utilisateur**, voir le site :
**[bounadrame.github.io/csweb-community](https://bounadrame.github.io/csweb-community/)**
(source dans `docs-nextra/`).

Les deux se complètent plutôt qu'elles ne se répètent : le site donne le
parcours guidé, ces fichiers gardent le détail complet — procédures pas à pas,
cas limites, décisions d'architecture.

---

## Déploiement et configuration

| Document | Contenu |
|---|---|
| [DOCKER-DEPLOYMENT](DOCKER-DEPLOYMENT.md) | Déploiement Docker, profils, volumes |
| [CONFIGURATION-MULTI-DATABASE](CONFIGURATION-MULTI-DATABASE.md) | PostgreSQL, MySQL, SQL Server en cible de breakout |
| [ARCHITECTURE-FLEXIBLE](ARCHITECTURE-FLEXIBLE.md) | Modes local / distant, choix d'architecture |
| [NOTES-CONFIGURATION-CSWEB](NOTES-CONFIGURATION-CSWEB.md) | Notes de configuration CSWeb |
| [INSTALLATION-CSWEB-VANILLA](INSTALLATION-CSWEB-VANILLA.md) | Installation du CSWeb amont, pour référence |

## Breakout

| Document | Contenu |
|---|---|
| [MIGRATION-BREAKOUT-SELECTIF](MIGRATION-BREAKOUT-SELECTIF.md) | Passage du breakout global au breakout par dictionnaire |
| [tuto_remote_breakout](tuto_remote_breakout.md) | Breakout vers une base distante |

## Intégration

| Document | Contenu |
|---|---|
| [WEBHOOKS-INTEGRATION](WEBHOOKS-INTEGRATION.md) | Webhooks : déclenchement, statut, logs |
| [CSWEB-OAUTH-AUTHENTICATION](CSWEB-OAUTH-AUTHENTICATION.md) | Authentification OAuth2 de l'API |

## Maintenance du fork

| Document | Contenu |
|---|---|
| [MIGRATION-VERSION-UPSTREAM](MIGRATION-VERSION-UPSTREAM.md) | Porter la couche Community sur une nouvelle version de CSWeb |

Voir aussi [.github/CONTRIBUTING.md](../.github/CONTRIBUTING.md) pour la
politique de versioning, le modèle de branches et le processus de release.
