<?php
/**
 * Utilitaires IP — calculs CIDR centralisés (testables).
 * Pourquoi : fiabiliser network()/overlaps() pour la validation
 * (collisions guest/VLAN) et la génération DHCP.
 */
declare(strict_types=1);

namespace App\Services;

class IpUtils
{
    /** Convertit un masque (CIDR ou dépointé) en préfixe 0-32, null si invalide. */
    public static function toCidr(string|int $mask): ?int
    {
        $s = trim((string) $mask);
        // CIDR numérique strict : 0-32 uniquement.
        if (preg_match('/^\d{1,2}$/', $s)) {
            $n = (int) $s;
            return ($n >= 0 && $n <= 32) ? $n : null;
        }
        // Masque dépointé : 4 octets 0-255, bits contigus.
        if (filter_var($s, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && str_contains($s, '.')) {
            $long = ip2long($s);
            if ($long === false) {
                return null;
            }
            $long = (int) sprintf('%u', $long);
            $bits = substr_count(decbin($long), '1');
            // Vérifie la contiguïté : masque reconstruit == masque donné.
            $rebuilt = $bits === 0 ? 0 : ((0xFFFFFFFF << (32 - $bits)) & 0xFFFFFFFF);
            if ((int) sprintf('%u', ip2long($s)) === (int) sprintf('%u', $rebuilt)) {
                return $bits;
            }
            return null;
        }
        return null;
    }

    /** Adresse réseau d'un couple IP/CIDR (false si IP invalide). */
    public static function network(string $ip, int $cidr): string|false
    {
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }
        $cidr = max(0, min(32, $cidr));
        $mask = $cidr === 0 ? 0 : ((-1 << (32 - $cidr)) & 0xFFFFFFFF);
        return (string) long2ip($long & $mask);
    }

    /** Première et dernière adresse du sous-réseau (bornes incluses). */
    public static function range(string $ip, int $cidr): array|false
    {
        $net = self::network($ip, $cidr);
        if ($net === false) {
            return false;
        }
        $netLong = (int) sprintf('%u', ip2long($net));
        $size = $cidr === 32 ? 1 : (2 ** (32 - $cidr));
        $first = $netLong;
        $last = $netLong + $size - 1;
        return [long2ip($first), long2ip($last)];
    }

    /** Vrai si $ip appartient au sous-réseau cidr. */
    public static function inSubnet(string $ip, string $netIp, int $cidr): bool
    {
        $net = self::network($netIp, $cidr);
        $target = self::network($ip, $cidr);
        return $net !== false && $target !== false && $net === $target;
    }

    /** Vrai si deux sous-réseaux se chevauchent. */
    public static function overlaps(string $ipA, int $cidrA, string $ipB, int $cidrB): bool
    {
        $rA = self::range($ipA, $cidrA);
        $rB = self::range($ipB, $cidrB);
        if ($rA === false || $rB === false) {
            return false;
        }
        $a1 = (int) sprintf('%u', ip2long($rA[0]));
        $a2 = (int) sprintf('%u', ip2long($rA[1]));
        $b1 = (int) sprintf('%u', ip2long($rB[0]));
        $b2 = (int) sprintf('%u', ip2long($rB[1]));
        return max($a1, $b1) <= min($a2, $b2);
    }

    /** Compare deux IPv4 (-1, 0, 1), false si invalide. */
    public static function cmp(string $a, string $b): int|false
    {
        $la = ip2long($a);
        $lb = ip2long($b);
        if ($la === false || $lb === false) {
            return false;
        }
        $ua = (int) sprintf('%u', $la);
        $ub = (int) sprintf('%u', $lb);
        return $ua <=> $ub;
    }

    /** Parse "10.10.10.0/24" en [ip, cidr], false si invalide. */
    public static function parseCidr(string $cidrNotation): array|false
    {
        $parts = explode('/', trim($cidrNotation));
        if (count($parts) !== 2) {
            return false;
        }
        [$ip, $mask] = $parts;
        if (!filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        $cidr = self::toCidr(trim($mask));
        return $cidr === null ? false : [trim($ip), $cidr];
    }
}
