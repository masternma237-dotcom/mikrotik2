# TP — Cybercafé (Douala)

**Public** : techniciens du support, administrateurs de cybercafé.  
**Durée** : ~45 min.  
**Objectif** : séparer le trafic client invité de la liaison d'accès.

## Configuration cible

| Section | Valeur |
|---|---|
| Bridge accès | `bridge-isp` (port `ether1` = WAN) |
| Bridge invité | `bridge-guest` (ports `ether2`, `sfp1`, `ether3`) |
| Wi-Fi client | `wifi2`, `Cybercafe-Douala`, WPA3-Personal |
| Accès | `10.0.0.1/24` via DHCP présent |
| Invité | `10.10.11.1/24`, isolation par `drop` sur le forward |
| LAN stocké | `192.168.1.1/24`, DHCP désactivé (serveur dédié) |
| NAT | masquerade sur `WAN` |

## Étapes

1. **Sécurité** : profil `Strict` (SSH/WinBox uniquement depuis le LAN).
2. **Bridges** : créer `bridge-isp` puis `bridge-guest`; éviter les conflits de port.
3. **Invité** : activer la case `Réseau invité`, puis vérifier le sous-réseau saisi.
4. **Wi-Fi** : changer `wifi2` pour une fréquence adaptée (`5G`/`2.4G`) — à confirmer en production.
5. **Réseau** : ajouter la passerelle LAN et le serveur DNS.
6. **Validation** : générer, puis vérifier sur un routeur `test` que le ping de
   `10.10.11.10` est filtré vers le LAN.

## Points de vigilance

- Un port ne peut appartenir qu'à un seul bridge.
- Les plages DHCP invitées et LAN ne doivent pas chevaucher.
- Ne pas importer sur un routeur en production sans sauvegarde.
- Le fichier s'appelle `Cybercafe-Douala-<date>.rsc`.

## Résultat attendu

Un script avec deux bridges, une isolation réseau, un profil WPA3 et un profil pare-feu Strict.
