<?php
/**
 * Validation serveur complete - Generateur MikroTik V2.
 * Deux couches : syntaxe (filter_var + bornes) puis coherence
 * (sous-reseau, unicite, collisions guest/VLAN).
 * Regles en francais, cles stables pour le front.
 */
declare(strict_types=1);
namespace App\Services;
class ValidationService
{
    private const PROFILES = ['Home','Office','Strict'];
    private const MAX_IDENTITY = 64;
    private const MAX_SSID = 32;
    private array $config;
    public function __construct(array $config = []) {
        $this->config = $config;
        if ($this->config === []) {
            $f = dirname(__DIR__, 2) . '/config/app.php';
            if (is_file($f)) { $l = require $f; if (is_array($l)) $this->config = $l; }
        }
    }
    public function validate(array $data): array {
        $errors = [];
        $identity = trim((string)($data['identity'] ?? ''));
        if ($identity === '') $errors['identity'] = 'Nom du routeur requis.';
        elseif (mb_strlen($identity) > self::MAX_IDENTITY) $errors['identity'] = 'Nom du routeur trop long (64 caracteres max).';
        elseif (preg_match('/[\r\n\x00-\x1F\x7F]/', $identity)) $errors['identity'] = 'Nom du routeur : caracteres de controle interdits.';
        if (!in_array($data['security_profile'] ?? 'Home', self::PROFILES, true)) $errors['security_profile'] = 'Profil invalide (Home, Office ou Strict).';
        // Bridges
        if (!is_array($data['bridges'] ?? null)) $errors['bridges'] = 'Bridges invalides.';
        else {
            $names = []; $usedPorts = [];
            foreach ($data['bridges'] as $i => $b) {
                if (!is_array($b)) { $errors["bridges.$i"] = 'Bridge invalide.'; continue; }
                $name = trim((string)($b['name'] ?? ''));
                if ($name === '') $errors["bridges.$i.name"] = 'Nom de bridge requis.';
                elseif (!preg_match('/^[a-zA-Z0-9\-_]{1,32}$/', $name)) $errors["bridges.$i.name"] = 'Nom de bridge invalide (lettres, chiffres, - et _).';
                elseif (in_array(strtolower($name), $names, true)) $errors["bridges.$i.name"] = 'Nom de bridge en double.';
                else $names[] = strtolower($name);
                $ports = $b['ports'] ?? [];
                if (!is_array($ports)) { $errors["bridges.$i.ports"] = 'Ports invalides.'; continue; }
                foreach ($ports as $p) {
                    $port = trim((string)$p);
                    if (!preg_match('/^(ether\d{1,2}|sfp\d{1,2}|wifi\d{1,2}|wlan\d{1,2})$/', $port)) { $errors["bridges.$i.ports"] = "Port \u00ab $port \u00bb invalide (ether1-48, sfp1, wifi1...)."; break; }
                    $k = strtolower($port);
                    if (isset($usedPorts[$k])) { $errors["bridges.$i.ports"] = "Port \u00ab $port \u00bb deja assigne a un autre bridge."; break; }
                    $usedPorts[$k] = true;
                }
            }
        }
        // Wi-Fi
        if (!is_array($data['wifi'] ?? null)) $errors['wifi'] = 'Wi-Fi invalide.';
        else {
            $ifaces = [];
            foreach ($data['wifi'] as $i => $w) {
                if (!is_array($w)) { $errors["wifi.$i"] = 'Entree Wi-Fi invalide.'; continue; }
                $iface = trim((string)($w['iface'] ?? ''));
                if ($iface === '') $errors["wifi.$i.iface"] = 'Interface Wi-Fi requise.';
                elseif (!preg_match('/^[a-zA-Z0-9\-_]{1,32}$/', $iface)) $errors["wifi.$i.iface"] = 'Interface Wi-Fi invalide.';
                elseif (in_array(strtolower($iface), $ifaces, true)) $errors["wifi.$i.iface"] = 'Interface Wi-Fi en double.';
                else $ifaces[] = strtolower($iface);
                $ssid = trim((string)($w['ssid'] ?? ''));
                if ($ssid === '') $errors["wifi.$i.ssid"] = 'SSID requis.';
                elseif (mb_strlen($ssid) > self::MAX_SSID) $errors["wifi.$i.ssid"] = 'SSID trop long (32 caracteres max).';
                if (mb_strlen((string)($w['password'] ?? '')) < 8) $errors["wifi.$i.password"] = 'Mot de passe trop court (8 caracteres min).';
                if (!in_array($w['security'] ?? 'WPA2-Personal', ['WPA2-Personal','WPA3-Personal'], true)) $errors["wifi.$i.security"] = 'Securite Wi-Fi invalide.';
            }
        }
        // Sous-reseaux guest/vlan parametrables
        $guestSubnet = $this->resolveSubnet($data['guest_subnet'] ?? null, 'guest_default', '10.10.10.0/24');
        $vlanSubnet = $this->resolveSubnet($data['vlan_subnet'] ?? null, 'vlan_default', '10.100.100.0/24');
        if (!empty($data['guest']) && $guestSubnet === false) $errors['guest_subnet'] = 'Sous-reseau invite invalide (ex. 10.10.10.0/24).';
        if (!empty($data['vlan']) && $vlanSubnet === false) $errors['vlan_subnet'] = 'Sous-reseau VLAN invalide (ex. 10.100.100.0/24).';
        if (!empty($data['guest']) && !empty($data['vlan']) && $guestSubnet !== false && $vlanSubnet !== false) {
            if (IpUtils::overlaps($guestSubnet[0], $guestSubnet[1], $vlanSubnet[0], $vlanSubnet[1])) $errors['vlan_subnet'] = 'Le sous-reseau VLAN chevauche le sous-reseau invite.';
        }
        // Reseaux
        if (!is_array($data['networks'] ?? null)) $errors['networks'] = 'Reseaux invalides.';
        else {
            $parsed = [];
            foreach ($data['networks'] as $i => $n) {
                if (!is_array($n)) { $errors["networks.$i"] = 'Reseau invalide.'; continue; }
                $addr = trim((string)($n['address'] ?? ''));
                if (!filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { $errors["networks.$i.address"] = 'Adresse IP invalide.'; continue; }
                $cidr = IpUtils::toCidr($n['mask'] ?? '');
                if ($cidr === null) { $errors["networks.$i.mask"] = 'Masque invalide (0-32 ou 255.255.255.0).'; continue; }
                $parsed[$i] = [$addr, $cidr];
                $gw = trim((string)($n['gateway'] ?? ''));
                if ($gw !== '') {
                    if (!filter_var($gw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $errors["networks.$i.gateway"] = 'Passerelle invalide.';
                    elseif (!IpUtils::inSubnet($gw, $addr, $cidr)) $errors["networks.$i.gateway"] = 'Passerelle hors du sous-reseau.';
                }
                $dns = trim((string)($n['dns'] ?? ''));
                if ($dns !== '' && !filter_var($dns, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $errors["networks.$i.dns"] = 'DNS invalide.';
                if (!empty($n['dhcp'])) {
                    $rs = trim((string)($n['range_start'] ?? ''));
                    $re = trim((string)($n['range_end'] ?? ''));
                    if (!filter_var($rs, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $errors["networks.$i.range_start"] = 'Debut de plage DHCP invalide.';
                    if (!filter_var($re, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $errors["networks.$i.range_end"] = 'Fin de plage DHCP invalide.';
                    if (filter_var($rs, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($re, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $cmp = IpUtils::cmp($rs, $re);
                        if ($cmp === false || $cmp > 0) $errors["networks.$i.range"] = 'Plage DHCP inversee (debut > fin).';
                        elseif (!IpUtils::inSubnet($rs, $addr, $cidr) || !IpUtils::inSubnet($re, $addr, $cidr)) $errors["networks.$i.range"] = 'Plage DHCP hors du sous-reseau.';
                    }
                }
                if (!empty($data['guest']) && $guestSubnet !== false && IpUtils::overlaps($addr, $cidr, $guestSubnet[0], $guestSubnet[1])) $errors["networks.$i.address"] = 'Chevauche le sous-reseau invite (' . $guestSubnet[0] . '/' . $guestSubnet[1] . ').';
                if (!empty($data['vlan']) && $vlanSubnet !== false && IpUtils::overlaps($addr, $cidr, $vlanSubnet[0], $vlanSubnet[1])) $errors["networks.$i.address"] = 'Chevauche le sous-reseau VLAN (' . $vlanSubnet[0] . '/' . $vlanSubnet[1] . ').';
            }
            $keys = array_keys($parsed);
            foreach ($keys as $a => $ka) {
                foreach (array_slice($keys, $a + 1) as $kb) {
                    [$ipA, $cA] = $parsed[$ka]; [$ipB, $cB] = $parsed[$kb];
                    if (IpUtils::overlaps($ipA, $cA, $ipB, $cB)) $errors["networks.$kb.address"] = 'Chevauche le reseau n' . ($ka + 1) . '.';
                }
            }
        }
        return $errors;
    }
    private function resolveSubnet(mixed $value, string $configKey, string $fallback): array|false {
        $raw = (is_string($value) && trim($value) !== '') ? trim($value) : (($this->config['reserved'][$configKey] ?? null) ?? $fallback);
        $p = IpUtils::parseCidr((string)$raw);
        return $p === false ? false : $p;
    }
}
