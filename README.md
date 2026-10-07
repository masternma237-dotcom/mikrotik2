# Générateur MikroTik RouterOS V2

Application web de formation qui génère un **script RouterOS (.rsc)** à partir d'un
formulaire : identité du routeur, profil de sécurité, bridges, Wi‑Fi (WPA2/WPA3),
réseaux IP/DHCP, pare‑feu, NAT, réseau invité, VLAN et NTP.

Interface en français, backend **PHP 8.2 vanilla (MVC maison, sans framework ni Composer)**.

## Architecture

```
index.php                          Front controller (require_once, chemins portables)
App/
├── controller/GeneratorController.php   GET → vue | POST JSON → validation → génération
├── Service/
│     ValidationService.php              Règles serveur (identité, SSID, mdp ≥ 8, IP/masque)
│     RouterOSGenerator.php              Émission du script RouterOS v7
│     TemplateEngine.php                 Rendu de fichier PHP (aucun eval)
├── View/layout.php                      Page unique (formulaire + window.APP_DEFAULTS)
assets/
├── css/style.css                        Thème sombre responsive
└── js/app.js                            État du formulaire + appel de l'API (aucune génération locale)
tests/
├── run_tests.php                        Tests CLI (ValidationService, générateur, templates)
└── fixtures/template.php
```

**Principe : la source de vérité est le serveur.** Le front envoie le formulaire en JSON
(`POST` sur la même URL), affiche le script retourné ou les erreurs de validation (HTTP 422)
dans un bloc dédié. Aucune logique de génération n'est dupliquée côté client.

## Installation (XAMPP)

1. Placer le dossier dans `htdocs/` (déjà le cas : `htdocs/formations/mikrotik2`).
2. Démarrer Apache (PHP ≥ 8.1) si vous exposer l'intégralité du dossier.
3. Pour la documentation seule (`public/`), exposer uniquement `public/` (voir
   `nginx.conf.example`). Les entités sensibles (`config/`, `App/`, `storage/`,
   `presets` natifs) restent en dehors de la racine HTTP.
4. Ouvrir l'URL racine (par exemple `http://localhost/formations/mikrotik2/` ou
   votre serveur virtuel pointant sur `public/`).

> L'application doit être servie par HTTP : l'appel `fetch()` ne fonctionne pas en `file://`.

## API

`POST /` avec un corps JSON :

```json
{
  "identity": "R-Office",
  "security_profile": "Home | Office | Strict",
  "bridges": [{"name": "bridge-lan", "ports": ["ether2"]}],
  "wifi": [{"iface": "wifi1", "ssid": "SSID", "security": "WPA2-Personal", "password": "motdepasse"}],
  "networks": [{"interface": "bridge-lan", "address": "192.168.88.1", "mask": 24,
                "gateway": "192.168.88.1", "dns": "192.168.88.1", "dhcp": true,
                "range_start": "192.168.88.10", "range_end": "192.168.88.254"}],
  "ntp": false, "nat": true, "firewall": true, "guest": false, "vlan": false
}
```

Réponses : `200 {ok:true, script}` — `422 {ok:false, errors}` — `413` corps trop volumineux — `500` erreur interne.

## Sécurité

- Validation systématique des entrées côté serveur (`ValidationService`).
- Échappement des valeurs dans les chaînes RouterOS ; noms d'objets filtrés sur `[a-zA-Z0-9-_]`
  (aucun séparateur de commande possible dans le script généré).
- Échappement HTML strict côté client (`escHtml`) lors des interpolations dans le DOM.
- `TemplateEngine` inclut des fichiers du dépôt — **aucun `eval()`**.
- Corps JSON borné à 256 Ko.
- Aucun secret ni identifiant en dur ; aucun état de session (pas de CSRF applicable).

## Tests

```bash
php tests/run_tests.php      # code retour 0 = succès
```

Couvre : validations (entrées valides et invalides), calcul des sous-réseaux (dont /20 et
masques dépointés), émission Wi‑Fi/pare‑feu/invité/VLAN, échappement et anti‑injection,
rendu des templates.

## Points d'attention

- Les règles générées visent RouterOS **v7** ; vérifier la syntaxe Wi‑Fi selon la version
  exacte du routeur cible avant import en production.
- Tester d'abord avec `/system reset-configuration` disponible (résultat de formation) —
  importer un `.rsc` en production exige une sauvegarde préalable.