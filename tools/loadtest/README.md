# Test de charge de la synchronisation

`csweb_sync_load.py` simule des enquêteurs qui synchronisent en parallèle par
l'API CSPro de CSWeb : jeton OAuth, envoi de cas compressés (deflate, comme
CSEntry), téléchargement optionnel. Les cas envoyés sont copiés de cas réels du
dictionnaire (uuid, clé et horloge régénérés). Python 3.8+, aucune dépendance.

> **Le script écrit de vrais cas.** À lancer uniquement sur un serveur de
> pré-production, avec un dictionnaire de test copié de celui de l'opération,
> à vider après le test.

```bash
python3 csweb_sync_load.py --url https://csweb-preprod.exemple.org \
  --dict RGPH_TEST_DICT --user admin --password '...' \
  --users-file comptes.csv \
  --enumerators 500 --cases-per-sync 4 --duration 1800 --ramp 300 \
  --think 30 --download 20 --json rapport.json
```

- `--users-file` : CSV `utilisateur,mot_de_passe`, un compte par enquêteur
  simulé. Sans lui, tous partagent `--user` et la limite de connexions par
  compte renvoie des HTTP 429 : ce n'est pas représentatif du terrain.
- Le rapport donne synchronisations/s, cas/s, taille envoyée par cas, temps
  p50/p95 et erreurs. Code de sortie 0 si erreurs < 1 % et p95 d'envoi < 10 s.

Méthode complète, formules et scénarios : guide
[Dimensionnement](https://bounadrame.github.io/csweb-community/guides/deployment/sizing) (valable pour les lignes 8.0.x à partir de 8.0.1 et 8.1.x).
