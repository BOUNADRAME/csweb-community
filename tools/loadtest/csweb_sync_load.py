#!/usr/bin/env python3
"""
CSWeb Community - test de charge de la synchronisation (API CSPro).

Simule N enqueteurs qui synchronisent en parallele, comme des tablettes CSPro :
  1. POST /api/token                      (authentification OAuth, une fois par enqueteur)
  2. POST /api/dictionaries/{dict}/cases  (envoi de K cas, uuid et cle regeneres)
  3. GET  /api/dictionaries/{dict}/cases  (telechargement d'un lot, optionnel)

Les cas envoyes sont construits a partir de cas reels du pilote : le script
telecharge un echantillon du dictionnaire (ou lit --template) et le duplique.
Python 3.8+, bibliotheque standard uniquement.

A lancer UNIQUEMENT sur un serveur de test ou de pre-production : le script
ecrit de vrais cas dans le dictionnaire cible. Utiliser un dictionnaire dedie
au test (copie du dictionnaire de l'operation) et le vider apres.

Exemple :
  python3 csweb_sync_load.py --url https://csweb-test.exemple.org \
      --dict RGPH_MENAGE_DICT --user loadtest --password '...' \
      --enumerators 200 --cases-per-sync 15 --duration 600 --ramp 120
"""
import argparse, json, random, ssl, statistics, sys, threading, time, uuid, zlib
import urllib.request, urllib.error

lock = threading.Lock()
stats = {"token": [], "upload": [], "download": [], "errors": {}, "cases_sent": 0, "bytes_sent": 0}


def call(method, url, body=None, headers=None, ctx=None, timeout=300):
    req = urllib.request.Request(url, data=body, method=method, headers=headers or {})
    t0 = time.perf_counter()
    try:
        with urllib.request.urlopen(req, context=ctx, timeout=timeout) as r:
            data = r.read()
            return r.status, data, dict(r.headers), time.perf_counter() - t0
    except urllib.error.HTTPError as e:
        return e.code, e.read(), dict(e.headers), time.perf_counter() - t0
    except Exception as e:  # timeout, connexion refusee...
        return 0, str(e).encode(), {}, time.perf_counter() - t0


def err(kind):
    with lock:
        stats["errors"][kind] = stats["errors"].get(kind, 0) + 1


def token(a, ctx, user=None, password=None):
    body = json.dumps({"client_id": "cspro_android", "client_secret": "cspro", "grant_type": "password",
                       "username": user or a.user, "password": password or a.password}).encode()
    st, data, _, dt = call("POST", a.url + "/api/token", body, {"Content-Type": "application/json"}, ctx)
    with lock:
        stats["token"].append(dt)
    if st != 200:
        err(f"token HTTP {st}")
        return None
    return json.loads(data)["credentials"]["access_token"]


def load_template(a, ctx):
    if a.template:
        with open(a.template, encoding="utf-8") as f:
            return json.load(f)
    tok = token(a, ctx)
    if not tok:
        sys.exit("Authentification impossible : verifier --user / --password.")
    st, data, _, _ = call("GET", f"{a.url}/api/dictionaries/{a.dict}/cases", None,
                          {"Authorization": "Bearer " + tok, "x-csw-case-range-count": "50"}, ctx)
    if st not in (200, 206):  # 206 = lot partiel
        sys.exit(f"Lecture des cas modeles impossible (HTTP {st}) : {data[:200]!r}")
    cases = json.loads(data)
    if not cases:
        sys.exit("Le dictionnaire ne contient aucun cas : fournir --template ou envoyer quelques cas du pilote.")
    return cases


def enumerator(i, a, ctx, template, stop_at):
    device = f"loadtest-{i:05d}"
    # Un compte par enqueteur, comme sur le terrain (round-robin sur --users-file).
    user, password = a.accounts[i % len(a.accounts)]
    tok = token(a, ctx, user, password)
    if not tok:
        return
    rnd = random.Random(i)
    while time.time() < stop_at:
        batch = []
        for _ in range(a.cases_per_sync):
            c = json.loads(json.dumps(rnd.choice(template)))
            c["uuid"] = str(uuid.uuid4())
            k = str(c.get("key", ""))  # meme longueur que la cle du dictionnaire
            c["key"] = "".join(str(rnd.randint(0, 9)) if ch.isdigit() else ch for ch in k) or uuid.uuid4().hex[:8]
            c["label"] = f"{a.key_prefix} {c['key']}"
            c["clock"] = [{"deviceId": device, "revision": 1}]
            batch.append(c)
        raw = json.dumps(batch).encode()
        body = zlib.compress(raw)  # deflate RFC 1950, comme le client CSPro
        hdr = {"Authorization": "Bearer " + tok, "Content-Type": "application/json",
               "Content-Encoding": "deflate", "x-csw-device": device}
        st, data, _, dt = call("POST", f"{a.url}/api/dictionaries/{a.dict}/cases", body, hdr, ctx)
        if st == 401:  # jeton expire
            tok = token(a, ctx, user, password) or tok
            continue
        with lock:
            stats["upload"].append(dt)
            if st == 200:
                stats["cases_sent"] += len(batch)
                stats["bytes_sent"] += len(body)
        if st != 200:
            err(f"upload HTTP {st}")
        if a.download:
            st, _, _, dt = call("GET", f"{a.url}/api/dictionaries/{a.dict}/cases", None,
                                {"Authorization": "Bearer " + tok, "x-csw-case-range-count": str(a.download)}, ctx)
            with lock:
                stats["download"].append(dt)
            if st not in (200, 206):
                err(f"download HTTP {st}")
        time.sleep(rnd.uniform(a.think * 0.5, a.think * 1.5))


def pct(xs, p):
    if not xs:
        return float("nan")
    xs = sorted(xs)
    return xs[min(len(xs) - 1, int(round(p / 100 * (len(xs) - 1))))]


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--url", required=True, help="URL de base de CSWeb, sans /api (ex. http://localhost:8080)")
    p.add_argument("--dict", required=True, help="Dictionnaire de test (copie du dictionnaire de l'operation)")
    p.add_argument("--user", required=True, help="Compte utilise pour lire les cas modeles")
    p.add_argument("--password", required=True)
    p.add_argument("--users-file", help="CSV 'utilisateur,mot_de_passe' : un compte par enqueteur simule. "
                   "Sans ce fichier, tous les enqueteurs partagent --user, ce qui declenche la limite "
                   "de connexions par compte (HTTP 429) : ce n'est pas representatif du terrain.")
    p.add_argument("--enumerators", type=int, default=50, help="Nombre d'enqueteurs simules en parallele")
    p.add_argument("--cases-per-sync", type=int, default=10, help="Cas envoyes par synchronisation")
    p.add_argument("--duration", type=int, default=300, help="Duree du test en secondes")
    p.add_argument("--ramp", type=int, default=60, help="Montee en charge : demarrage etale sur N secondes")
    p.add_argument("--think", type=float, default=5.0, help="Pause moyenne entre deux synchronisations (s)")
    p.add_argument("--download", type=int, default=0, help="Si > 0, telecharge N cas apres chaque envoi")
    p.add_argument("--template", help="Fichier JSON de cas modeles (sinon lus dans le dictionnaire)")
    p.add_argument("--key-prefix", default="LT", help="Prefixe des cles generees")
    p.add_argument("--insecure", action="store_true", help="Ne pas verifier le certificat TLS (test uniquement)")
    p.add_argument("--json", help="Ecrire le rapport en JSON dans ce fichier")
    a = p.parse_args()
    a.url = a.url.rstrip("/")
    a.accounts = [(a.user, a.password)]
    if a.users_file:
        with open(a.users_file, encoding="utf-8") as f:
            a.accounts = [tuple(l.strip().split(",", 1)) for l in f if l.strip() and not l.startswith("#")]
    ctx = ssl._create_unverified_context() if a.insecure else None

    template = load_template(a, ctx)
    print(f"{len(template)} cas modeles, {a.enumerators} enqueteurs, {a.cases_per_sync} cas/sync, "
          f"{a.duration}s (montee {a.ramp}s)")
    t0 = time.time()
    stop_at = t0 + a.duration
    threads = []
    for i in range(a.enumerators):
        th = threading.Thread(target=enumerator, args=(i, a, ctx, template, stop_at), daemon=True)
        th.start()
        threads.append(th)
        if a.ramp:
            time.sleep(a.ramp / a.enumerators)
    for th in threads:
        th.join(timeout=max(1, stop_at - time.time() + 360))
    elapsed = time.time() - t0

    up = stats["upload"]
    report = {
        "enumerators": a.enumerators, "duration_s": round(elapsed, 1),
        "syncs": len(up), "syncs_per_s": round(len(up) / elapsed, 2),
        "cases_sent": stats["cases_sent"], "cases_per_s": round(stats["cases_sent"] / elapsed, 2),
        "payload_kb_per_case": round(stats["bytes_sent"] / 1024 / max(1, stats["cases_sent"]), 2),
        "upload_p50_s": round(pct(up, 50), 3), "upload_p95_s": round(pct(up, 95), 3),
        "upload_max_s": round(max(up), 3) if up else None,
        "token_p95_s": round(pct(stats["token"], 95), 3),
        "download_p95_s": round(pct(stats["download"], 95), 3) if stats["download"] else None,
        "errors": stats["errors"],
        "error_rate": round(sum(stats["errors"].values()) / max(1, len(up) + len(stats["token"])), 4),
    }
    print(json.dumps(report, indent=2, ensure_ascii=False))
    if a.json:
        with open(a.json, "w", encoding="utf-8") as f:
            json.dump(report, f, indent=2, ensure_ascii=False)
    ok = report["error_rate"] < 0.01 and (report["upload_p95_s"] or 0) < 10
    print("VERDICT :", "OK" if ok else "A ANALYSER", "(seuils : erreurs < 1 %, p95 envoi < 10 s)")
    sys.exit(0 if ok else 1)


if __name__ == "__main__":
    main()
