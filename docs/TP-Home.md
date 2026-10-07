# TP — Générateur RouterOS V2 (Accueil / Maison)

**Public** : nomades, habitants, mises en réseau simples.  
**Durée** : ~30 min.  
**Objectif** : produire un script `.rsc` prêt à l'import sur un routeur MikroTik.

## Données d'exemple

| Champ | Valeur par défaut |
|---|---|
| Identité | `MikroTik-Router` |
| Profil | Home |
| Bridge LAN | `bridge-lan`, ports `ether2`, `ether3`, `sfp1` |
| Wi-Fi | `MikroTik-24` (WPA2-Personal) |
| IP LAN | `192.168.88.1/24` |
| DHCP | `.10` → `.254` |
| Pare-feu | activé (LAN autorisé) |
| NAT | activé |

Les sous-réseaux optionnels (invité `10.10.10.0/24`, VLAN `10.100.100.0/24`) sont prêts à être activés.

## Fiches à réaliser

1. **Identité** : changer `identity` en prenant un nom clair (objet autorisé : `[a-zA-Z0-9_-]`).
2. **Bridge** : cocher `sfp1` si le routeur dispose d'une interface SFP (port optique).
3. **Wi-Fi** : valider la longueur du mot de passe (8 caractères minimum) et le type de sécurité.
4. **Réseau** : vérifier que la passerelle et le DNS appartiennent au sous-réseau.
5. **Invité / VLAN** : décocher la case pour garder une configuration minimale.
6. **Pré-visualisation** : générer, copier le script puis l'imprimer pour le TP.

## Règles de sécurité à retenir

- Sauvegarder toujours le routeur (`/export file=backup`) avant importation.
- Générer sur le serveur ; le code est le seul à pouvoir valider.
- Lire les messages d'erreur du bloc `Erreurs` qui signalent les champs rejetés.

## Résultat attendu

Un fichier `<nom>-<AAAA-MM-JJ>.rsc` (ex. `MikroTik-Router-2026-10-07.rsc`) importable via
`import file=<fichier>.rsc` ou glissé-déposé dans le terminal ROS.
