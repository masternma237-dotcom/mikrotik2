#!/usr/bin/env python3
"""Générateur de fichiers de préconfiguration presets.

Utilisation :
    python3 presets.py

Ce script réécrit l'intégralité du contenu du dossier presets/ à partir de
la source de vérité en Python, afin de consolider la déclaration d'exclusion
à la racine du projet et d'homogénéiser l'indentation JSON entre toutes les
présentations.
"""

import json
import pathlib

BASE_DIR = pathlib.Path(__file__).resolve().parent
PRESETS_DIR = BASE_DIR / "presets"

#: Preset Home (Yaoundé)
HOME_YAOUNE = {
    "identity": "MikroTik-Router-Home",
    "security_profile": "Home",
    "guest_subnet": "10.10.10.0/24",
    "vlan_subnet": "10.100.100.0/24",
    "ntp": False,
    "nat": True,
    "guest": False,
    "vlan": False,
    "firewall": True,
    "bridges": [
        {
            "name": "bridge-lan",
            "ports": ["ether2", "ether3", "sfp1"],
        }
    ],
    "wifi": [
        {
            "iface": "wifi1",
            "ssid": "MikroTik-Home-24",
            "security": "WPA2-Personal",
            "password": "domicile-2026",
        }
    ],
    "networks": [
        {
            "interface": "bridge-lan",
            "address": "192.168.88.1",
            "mask": 24,
            "gateway": "192.168.88.1",
            "dns": "192.168.88.1",
            "dhcp": True,
            "range_start": "192.168.88.10",
            "range_end": "192.168.88.254",
        }
    ],
}

#: Preset Cybercafe (Douala)
CYBERCAFE = {
    "identity": "Cybercafe-Douala",
    "security_profile": "Strict",
    "guest_subnet": "10.10.11.0/24",
    "vlan_subnet": "10.100.110.0/24",
    "ntp": False,
    "nat": True,
    "guest": True,
    "vlan": False,
    "firewall": True,
    "bridges": [
        {
            "name": "bridge-isp",
            "ports": ["ether1"],
        },
        {
            "name": "bridge-guest",
            "ports": ["ether2", "sfp1", "ether3"],
        },
    ],
    "wifi": [
        {
            "iface": "wifi2",
            "ssid": "Cybercafe-Douala",
            "security": "WPA3-Personal",
            "password": "cafe-2026-secure",
        }
    ],
    "networks": [
        {
            "interface": "bridge-isp",
            "address": "10.0.0.1",
            "mask": 24,
            "gateway": "dhcp",
            "dhcp": True,
            "range_start": "10.0.0.10",
            "range_end": "10.0.0.254",
        },
        {
            "interface": "bridge-guest",
            "address": "10.10.11.1",
            "mask": 24,
            "gateway": "10.10.11.1",
            "dns": "10.10.11.1",
            "dhcp": True,
            "range_start": "10.10.11.10",
            "range_end": "10.10.11.254",
        },
        {
            "interface": "bridge-isp",
            "address": "192.168.1.1",
            "mask": 24,
            "gateway": "192.168.1.1",
            "dns": "192.168.1.1",
            "dhcp": False,
        },
    ],
}

#: Preset Salle-MINEFOP
SALLE_MINEFOP = {
    "identity": "Salle-MINEFOP",
    "security_profile": "Office",
    "guest_subnet": "10.10.12.0/24",
    "vlan_subnet": "10.100.120.0/24",
    "ntp": True,
    "nat": True,
    "guest": False,
    "vlan": True,
    "firewall": True,
    "bridges": [
        {
            "name": "bridge-lan",
            "ports": ["ether2", "ether3", "sfp1", "ether4"],
        }
    ],
    "wifi": [
        {
            "iface": "wifi3",
            "ssid": "Salle-MINEFOP-Train",
            "security": "WPA2-Personal",
            "password": "formation-2026",
        }
    ],
    "networks": [
        {
            "interface": "bridge-lan",
            "address": "10.100.120.1",
            "mask": 24,
            "gateway": "10.100.120.1",
            "dns": "10.100.120.1",
            "dhcp": True,
            "range_start": "10.100.120.10",
            "range_end": "10.100.120.100",
        },
        {
            "interface": "bridge-lan",
            "address": "192.168.50.1",
            "mask": 24,
            "gateway": "192.168.50.1",
            "dns": "192.168.50.1",
            "dhcp": False,
        },
    ],
}

PRESETS = {
    "home-yaounde.json": HOME_YAOUNE,
    "cybercafe-douala.json": CYBERCAFE,
    "salle-minefop.json": SALLE_MINEFOP,
}


def main() -> None:
    PRESETS_DIR.mkdir(parents=True, exist_ok=True)
    written = []
    for name, data in PRESETS.items():
        target = PRESETS_DIR / name
        payload = json.dumps(data, indent=2, ensure_ascii=False, sort_keys=False)
        target.write_text(payload + "\n", encoding="utf-8")
        written.append(target)
    print(f"[OK] {len(written)} preset(s) généré(s) :")
    for path in written:
        print(f"  - {path}")


if __name__ == "__main__":
    main()
