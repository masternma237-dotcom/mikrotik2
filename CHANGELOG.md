# CHANGELOG

## [2.1.0] — 2026-10-07

### Sécurité
- **Suppression de l'`eval()`** dans `TemplateEngine::render()` : rendu par `include`
  de fichiers du dépôt uniquement, avec gestion d'erreur (exception si fichier absent).
- **Échappement HTML strict côté client** (`escHtml`) : l'ancienne fonction ne neutralisait
  pas les guillemets en contexte d'attribut (vecteur d'injection DOM).
- **Échappement RouterOS corrigé** (backslash d'abord) et filtrage systématique des noms
  d'objets sur `[a-zA-Z0-9-_]` — impossible d'insérer un séparateur de commande.
- Corps JSON borné à 256 Ko (HTTP 413 au-delà) ; rejet propre d'un corps non JSON.
- Suppression d'un `session_start()` inutilisé.

### Corrigé
- **Double rendu / en-têtes cassés** : `index.php` rendait `layout.php` avant le contrôleur
  (HTML envoyé avant `Content-Type: application/json`, page affichée deux fois sur GET).
  Le rendu est désormais unique, dans le contrôleur.
- **Portabilité Linux** : chemins `require_once` alignés sur la casse réelle des dossiers
  (`App/controller`, `App/Service`, `App/View`).
- **Calcul d'adresse réseau sur 32 bits** : l'implémentation ne gérait que /8, /16, /24 —
  un /20 ou /23 produisait un `dhcp-server network` erroné. Masques dépointés acceptés.

### Ajouté
- **Génération serveur complète** : configurations de sécurité Wi‑Fi (WPA2‑PSK / WPA3‑SAE,
  menus `/interface wifi security` et `wireless security-profiles`), profil de sécurité
  appliqué au pare‑feu (Home/Office : LAN ouvert ; Strict : SSH + WinBox uniquement),
  pare‑feu deny‑by‑default activable, réseau invité isolé (10.10.10.0/24 + règle de drop),
  VLAN 100 de gestion avec `vlan-filtering`.
- Bascule **Pare-feu** dans l'interface et bloc d'affichage des erreurs de validation.
- **Tests CLI** : `tests/run_tests.php` (24 assertions : validations, sous-réseaux,
  génération, anti-injection, templates).
- `README.md` et ce `CHANGELOG.md`.

### Changé
- Le front n'est plus qu'un consommateur de l'API : la génération locale a été supprimée
  (source de vérité unique = serveur), avec synchronisation du formulaire sur chaque saisie.
## [2.2.0] — 2026-10-07

### Front (Lot 4)
- Vue unique en **partiels** (`App/View/partials/head/form/output.php`) : séparation
  en-tete / formulaire / sortie pour maintenabilité et import `.md` imprimable.
- Accessibilité : tous les champs portent `for`/`id`, `aria-live` sur le bloc erreurs,
  `role="status"` sur la notification de copie.
- Formulaire complet : identité, profil de sécurité, bridges (cochage `bridgePorts` + `sfp1`),
  Wi-Fi Wave2 (WPA2/WPA3), réseaux IP/DHCP, pare-feu, NAT, invité paramétrable,
  VLAN 100, backup-guard avant import.
- Pré-validation cliente miroir (identité, SSID/mot de passe Wifi, IP/masque/passerelle/DNS,
  plage DHCP) sans remplacer la validation serveur ; erreurs ciblées avec focus et `aria-invalid`.
- Gestion du fichier de sortie : `rscName()` (`{identity}-{AAAA-MM-JJ}.rsc`) et boutons
  Copier / Télécharger / Imprimer la fiche TP.
- Presets définis (`presets/*.json`) : home-yaounde, cybercafe-douala, salle-minefop.
- Docs TP ajoutées (`docs/TP-Home.md`, `TP-Cybercafe.md`, `Salle-MINEFOP.md`).
- Mise à jour de `assets/js/app.js` et `assets/css/style.css` (déployés dans `public/`).

### DevOps / Tests (Lot 5)
- Lint PHP `tests/ci.sh` et test CLI `tests/run_tests.php` : **44 assertions passées**.
- Refonte du routeur `public/` comme seule racine documentaire (XAMPP et Nginx).
- `nginx.conf.example` durci (`public/` docroot, `.log`/`.json` 404, socket PHP-FPM, cache assets).
- `App/View/layout.php` portable racine et docroot `public/`, versionnage des URLs CSS/JS.

### Présentation
- `CHANGELOG.md` structuré par lot (Sécurité, Front, DevOps, Présentation).
- Documentation courte et préparable pour les sessions de TP.
