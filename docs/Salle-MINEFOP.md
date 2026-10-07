# TP — Salle MINEFOP (formation)

**Public** : apprenants MINEFOP, formateurs, supports de cours.  
**Durée** : ~50 min.  
**Objectif** : sécuriser un réseau scolaire avec VLAN, DHCP et pare-feu.

## Configuration cible

| Section | Valeur |
|---|---|
| Bridge LAN | `bridge-lan` (ports `ether2`→`4`, `sfp1`) |
| Wi-Fi | `wifi3`, `Salle-MINEFOP-Train`, WPA2-Personal |
| Gestion VLAN | `vlan100` sur `10.100.100.1/24`, DHCP activé |
| DHCP stage | `10.100.120.10` → `.100` |
| DHCP Général | `192.168.50.1/24`, DHCP désactivé |
| NAT | masquerade |
| NTP | activé (horloge de formation) |
| Pare-feu | profil Office (LAN autorisé, refuse WAN) |

## Étapes

1. **Préparation** : recopier l'arborescence du projet et lancer `php tests/run_tests.php`.
2. **Identité** : saisir le nom du labo + date ; confirmer le profil `Office`.
3. **Bridge** : ajouter les ports `sfp1` si présent et dupliquer les câbles RSTP si besoin.
4. **VLAN** : cocher `VLAN 100 gestion` et saisir `10.100.120.0/24`.
5. **Wi-Fi** : mettre un mot de passe d'au moins 8 caractères (ex. `formation-2026`).
6. **Génération** : valider `J'ai sauvegardé le routeur` pour autoriser le pare-feu.
7. **Production** : sauvegarder, importer `<Salle-MINEFOP>-<date>.rsc`, puis tester `ping` & DHCP.

## Contrôles avant importation

- Aucun nom d'interface ou de bridge ne contient de caractères spéciaux.
- Les sous-réseaux `guest`, `vlan` et `lan` sont disjoints.
- Le mot de passe Wi-Fi est en dur dans le script ; changer après importation.
- Vérifier d'abord sur un routeur de démonstration, puis lancer une sauvegarde.
