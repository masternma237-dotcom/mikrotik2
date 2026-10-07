# Plan d'amélioration — Générateur MikroTik RouterOS V2

> Date : 07/10/2026 — Version cible : v2.2.0 et suivantes
> Stack : PHP 8.2 vanilla (MVC maison, sans framework ni Composer)
> Public : formation, contexte camerounais/institutionnel (français)

## 1. Besoin reformulé

L'application est saine en v2.1.0 : source de vérité serveur, validation
serveur, anti-injection RouterOS/DOM, tests CLI (24 assertions).

Objectif : passer de « outil de TP qui fonctionne » à « outil de formation
fiable, auditable et déployable sur Proxmox/Nginx », sans framework
ni dépendance lourde, interface en français.

## 2. État des lieux — revue structurée

### Critique — P0 (bloquant)

1. **Fuite d'information en `500`** : `GeneratorController::handle()` renvoie
   `$e->getMessage()` au client. Expose chemins et internals en production.
2. **Collisions IP non détectées** : `guestBlock()` impose `10.10.10.0/24` et
   `vlanBlock()` `10.100.100.0/24` sans vérifier les réseaux utilisateurs.
3. **Validation incomplète** : `ValidationService` ne contrôle ni
   `gateway/dns/range_start/range_end`, ni `range dans sous-réseau`,
   ni `gateway dans sous-réseau`, ni bornes `mask 0-32`, ni doublons noms/ports.
4. **Dépendance morte** : `RouterOSGenerator` n'utilise pas `TemplateEngine`
   injecté. Génération en concaténation difficile à maintenir/tester.

### Important — P1

5. **En-têtes sécurité absents** : pas de `Content-Security-Policy`,
   `X-Content-Type-Options: nosniff`, `X-Frame-Options`, `Referrer-Policy`.
   Pas de `405` hors GET/POST, pas de contrôle `Content-Type`.
6. **Corps non-JSON indistinguable du vide** : `json_decode` en échec =>
   `$input = []` => `422` générique au lieu de `400 JSON invalide`. Pas de log.
7. **Structure non-PSR-4** : `App/controller` minuscule vs `App\Controller`,
   `require_once` manuels dans `index.php`. Fragile sous Linux/Proxmox.
8. **`index.php` à la racine = docroot = code exposé**. Pas de `public/`
   séparé, pas d'exemple `nginx.conf` / `.htaccess`.
9. **Front** : `bridgePorts` en dur `ether1-8`, pas de pré-validation miroir,
   nom `.rsc` statique, pas de retour « copié », accessibilité faible
   (`label` sans `for`, pas d'`aria-live` sur `#errors`).
10. **Tests maison uniquement** : pas de test HTTP (200/400/413/422/500),
    pas de test JS, pas de CI `php -l`.

### Mineur — P2

11. CSS monolithe une ligne, pas de versionnement d'assets robuste,
    pas de mode impression fiche TP.
12. Pas de `config/`, pas de `APP_DEBUG`, pas de presets pédagogiques.

## 3. Architecture cible proposée

```text
mikrotik2/
 public/index.php              # seul point d'entree HTTP (docroot Nginx)
 public/assets/css|js/
 app/Controllers/GeneratorController.php
 app/Services/ValidationService.php
 app/Services/RouterOS/RouterOSGenerator.php  # orchestrateur
 app/Services/RouterOS/IpUtils.php            # cidr/network/overlaps
 app/Services/TemplateEngine.php
 app/Views/layout.php + partials/form-*.php
 config/app.php                # APP_DEBUG, MAX_BODY, version
 storage/logs/ (.gitignore)
 tests/run_tests.php + tests/HttpTest.php
 docs/TP-Home.md TP-Cybercafe.md + presets/*.json
 nginx.conf.example CHANGELOG.md README.md
```

Choix : pas de Composer/Laravel — surcout injustifie pour un generateur
stateless (~600 lignes). Autoloader natif `spl_autoload_register` + `public/`
suffit pour Proxmox/Nginx (code hors docroot). Pas de DB sauf historique.

## 4. Choix techniques justifies

| Decision | Justification |
|---|---|
| PHP vanilla + autoloader natif | Zero dependance, corrige fragilite `require_once` |
| Decoupage en Steps + `IpUtils` | Testabilite par section, overlaps centralises |
| Validation 2 couches | Syntaxe (`filter_var` + bornes) puis coherence (subnet, unicite) |
| Masquer `getMessage()` si prod | Message generique + log fichier avec ID correlation |
| Presets JSON versionnes, pas DB | `cybercafe-douala.json`, `salle-minefop.json`, sans migration |

Options ecartees :
* **A. Minimaliste (recommandee)** — P0+P1, ~2-3 jours, sans infra.
* **B. + Historique SQLite** — `generations(id, created_at, identity, profile,
  script_hash)` via migration. Utile suivi pedagogique, BDD a sauvegarder.
* **C. Migration Laravel** — deconseillee pour besoin stateless deja couvert.

## 5. Risques et securite

* Injection RouterOS : garder double barriere `id()` + `e()`, fuzz sur
  `; $() {} / []` et retours ligne.
* XSS : garder `escHtml` strict + `htmlspecialchars(ENT_QUOTES)` + future
  `CSP: default-src 'self'`.
* DoS memoire : garder `MAX_BODY 256 Ko` + rate-limit fichier 30 POST/min.
* Import `.rsc` destructeur : en-tete `# /export file=backup` + case
  « j'ai sauvegarde » cote UI.
* Profil `Home` : documenter que `LAN autorise` ouvre WinBox/API du LAN.


## 6. Plan sequence pret pour le mode Code

### Lot 1 — P0 Securite (bloquant)

1. `config/app.php` + `APP_DEBUG`, masquer `getMessage()` en prod,
   log `storage/logs/app.log`.
2. En-tetes `nosniff, SAMEORIGIN, Referrer-Policy, CSP`, `405` hors GET/POST,
   controle `Content-Type`, `400` si JSON invalide.
3. Rate-limit natif + test `413`.

### Lot 2 — P0 Coherence metier

4. `IpUtils` + `ValidationService` complete : gateway/dns/ranges,
   bornes masque, doublons, collision `10.10.10.0/24` et `10.100.100.0/24`.
5. Etendre `tests/run_tests.php` : +15 assertions (gateway hors subnet,
   overlap, range inverse).

### Lot 3 — P1 Generateur

6. Decoupage en Steps, orchestrateur testable, garde `safe-mode` en
   commentaire + `comment` horodate.
7. Rendre `guest/vlan` parametrables (sous-reseau propose + validation).

### Lot 4 — P1 Front/UX FR

8. Pre-validation JS miroir, `aria-live` erreurs, focus premiere erreur,
   feedback « Copie ! », nom `{identity}-{date}.rsc`, `bridgePorts` + `sfp1`.
9. Decoupage `layout.php` en partiels, CSS lisible + impression fiche TP.

### Lot 5 — DevOps/Docs

10. Docroot `public/`, autoloader natif, `controller` -> `Controllers`,
    `View` -> `Views` (alias BC), `nginx.conf.example`,
    CI `php -l + tests`, note `CHANGELOG.md` par lot.

### Lot 6 — Pedagogique

11. 3 presets JSON + docs TP (Home Yaounde / Cybercafe Douala / Salle MINEFOP),
    export fiche `.md` imprimable.

## 7. Validation humaine requise

* Changement docroot (impact URL XAMPP).
* Activation rate-limit (faux positif en salle).
* Renommage dossiers (impact chemins Proxmox).
* Commande destructive annoncee et confirmee avant execution.

## 8. Ordre recommande

Commencer par **Lot 1 + Lot 2** : seuls bloquants pour usage en salle
sans script invalide ni fuite d'information.

