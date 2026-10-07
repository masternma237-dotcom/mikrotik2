<?php
declare(strict_types=1);
namespace App\Services;
/**
 * Orchestrateur RouterOS v7 : Steps testables + TemplateEngine pour l'en-tete.
 * Sous-reseaux guest/VLAN parametrables (defauts 10.10.10.0/24, 10.100.100.0/24).
 */
class RouterOSGenerator {
    private array $config;
    public function __construct(private TemplateEngine $templates, array $config = []) {
        $this->config = $config;
        if ($this->config === []) {
            $f = dirname(__DIR__, 2) . '/config/app.php';
            if (is_file($f)) { $l = require $f; if (is_array($l)) $this->config = $l; }
        }
    }
    private function e(string $v): string {
        return str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $v);
    }
    private function id(string $v): string {
        return preg_replace('/[^a-zA-Z0-9\-_]/', '', $v) ?: '';
    }
    private function cidr(string|int $mask): int {
        $c = IpUtils::toCidr($mask);
        return $c === null ? 24 : $c;
    }
    private function network(string $ip, int $cidr): string {
        $n = IpUtils::network($ip, $cidr);
        return $n === false ? '0.0.0.0' : $n;
    }
    private function profile(array $data): string {
        return in_array($data['security_profile'] ?? '', ['Home','Office','Strict'], true) ? $data['security_profile'] : 'Home';
    }
    private function version(): string { return (string)($this->config['version'] ?? '2.2.0'); }
    private function subnetOrDefault(mixed $v, string $key, string $fb): string {
        $raw = (is_string($v) && trim($v) !== '') ? trim($v) : (($this->config['reserved'][$key] ?? null) ?? $fb);
        return IpUtils::parseCidr($raw) !== false ? $raw : $fb;
    }
    /** Decoupe un CIDR en infos utiles (gw = .1, pool .10-.254 borne au broadcast). */
    public function subnetInfo(string $cidrNotation): array {
        $p = IpUtils::parseCidr($cidrNotation);
        if ($p === false) $p = ['10.10.10.0', 24];
        [$ip, $cidr] = $p;
        $net = $this->network($ip, $cidr);
        $netL = (int)sprintf('%u', ip2long($net));
        $size = $cidr >= 31 ? (2 ** (32 - $cidr)) : (2 ** (32 - $cidr));
        $bcast = $netL + $size - 1;
        $gwL = min($netL + 1, $bcast - 1 > $netL ? $bcast - 1 : $netL + 1);
        $sL = min($netL + 10, $bcast - 1);
        $eL = max($bcast - 1, $sL);
        if ($cidr >= 31) { $gwL = $netL; $sL = $netL; $eL = $bcast; }
        return ['cidr' => $net . '/' . $cidr, 'network' => $net, 'mask' => $cidr, 'gateway' => long2ip($gwL), 'start' => long2ip($sL), 'end' => long2ip($eL)];
    }
    public function generate(array $data): string {
        $profile = $this->profile($data);
        $out = [];
        $out = array_merge($out, $this->headerStep($profile, (string)($data['identity'] ?? '')));
        $out[] = '/system identity set name="' . $this->e((string)($data['identity'] ?? '')) . '"';
        $out[] = '';
        $out = array_merge($out, $this->interfaceListsStep());
        $out = array_merge($out, $this->bridgesStep($data['bridges'] ?? []));
        $out = array_merge($out, $this->wifiStep($data['wifi'] ?? []));
        $out = array_merge($out, $this->ipDhcpStep($data['networks'] ?? []));
        if (!empty($data['guest'])) $out = array_merge($out, $this->guestStep($this->subnetOrDefault($data['guest_subnet'] ?? null, 'guest_default', '10.10.10.0/24')));
        if (!empty($data['vlan'])) $out = array_merge($out, $this->vlanStep($this->subnetOrDefault($data['vlan_subnet'] ?? null, 'vlan_default', '10.100.100.0/24')));
        if (!empty($data['firewall'])) $out = array_merge($out, $this->firewallStep($profile));
        if (!empty($data['nat'])) $out = array_merge($out, $this->natStep());
        if (!empty($data['ntp'])) $out = array_merge($out, $this->ntpStep());
        return rtrim(implode("\n", $out), "\n") . "\n";
    }
    public function headerStep(string $profile, string $identity): array {
        $tpl = dirname(__DIR__, 2) . '/App/View/templates/header.php';
        $legacy = dirname(__DIR__) . '/View/templates/header.php';
        $file = is_file($tpl) ? $tpl : (is_file($legacy) ? $legacy : null);
        if ($file !== null) {
            try { $h = $this->templates->render($file, ['version' => $this->version(), 'profile' => $profile, 'date' => gmdate('Y-m-d'), 'identity' => $identity]); return [$h]; } catch (\Throwable $e) { /* repli */ }
        }
        return ['# RouterOS script genere par le Generateur MikroTik V2', '# Profil de securite : ' . $profile];
    }
    public function interfaceListsStep(): array {
        return ["# Listes d'interfaces", '/interface list', 'add name=WAN', 'add name=LAN', ''];
    }
    public function bridgesStep(array $bridges): array {
        $out = ['# Bridges', '/interface bridge'];
        foreach ($bridges as $b) $out[] = 'add name="' . $this->e($this->id((string)($b['name'] ?? 'bridge'))) . '"';
        $out[] = '/interface bridge port';
        foreach ($bridges as $b) foreach (($b['ports'] ?? []) as $p) $out[] = 'add bridge="' . $this->e($this->id((string)($b['name'] ?? ''))) . '" interface=' . $this->id((string)$p);
        $out[] = '';
        return $out;
    }
    public function wifiStep(array $wifi): array {
        $out = ['# Wi-Fi (configurations de securite + interfaces)'];
        foreach ($wifi as $w) {
            $iface = $this->id((string)($w['iface'] ?? ''));
            $ssid = $this->e((string)($w['ssid'] ?? ''));
            $pass = $this->e((string)($w['password'] ?? ''));
            $sec = ($w['security'] ?? 'WPA2-Personal') === 'WPA3-Personal' ? 'WPA3-Personal' : 'WPA2-Personal';
            $auth = $sec === 'WPA3-Personal' ? 'wpa3-sae' : 'wpa2-psk';
            $out[] = '# ' . $iface . ' - ' . $sec;
            if (str_starts_with($iface, 'wifi')) {
                $out[] = '/interface wifi security';
                $out[] = 'add name="sec_' . $iface . '" authentication-types=' . $auth . ' passphrase="' . $pass . '"';
                $out[] = '/interface wifi set [find default-name=' . $iface . '] name=' . $iface . ' ssid="' . $ssid . '" security="sec_' . $iface . '"';
            } else {
                $out[] = '/interface wireless security-profiles';
                $out[] = 'add name="sec_' . $iface . '" mode=dynamic-keys authentication-types=' . $auth . ' wpa2-pre-shared-key="' . $pass . '" wpa-pre-shared-key="' . $pass . '"';
                $out[] = '/interface wireless set [find default-name=' . $iface . '] name=' . $iface . ' ssid="' . $ssid . '" security-profile="sec_' . $iface . '"';
            }
        }
        $out[] = '';
        return $out;
    }
    public function ipDhcpStep(array $networks): array {
        $out = ['# IP / DHCP'];
        foreach ($networks as $n) {
            $cidr = $this->cidr($n['mask'] ?? 24);
            $iface = $this->e((string)($n['interface'] ?? ''));
            $out[] = '/ip address add address=' . ($n['address'] ?? '') . '/' . $cidr . ' interface="' . $iface . '"';
            if (!empty($n['dhcp'])) {
                $pool = 'pool_' . $this->id((string)($n['interface'] ?? 'lan'));
                $out[] = '/ip pool add name="' . $pool . '" ranges=' . ($n['range_start'] ?? '') . '-' . ($n['range_end'] ?? '');
                $out[] = '/ip dhcp-server add name="dhcp_' . $this->id((string)($n['interface'] ?? 'lan')) . '" interface="' . $iface . '" address-pool="' . $pool . '" disabled=no';
                $out[] = '/ip dhcp-server network add address=' . $this->network((string)($n['address'] ?? '0.0.0.0'), $cidr) . '/' . $cidr . ' gateway=' . ($n['gateway'] ?? '') . ' dns-server=' . ($n['dns'] ?? '');
            }
        }
        $out[] = '';
        return $out;
    }
    public function guestStep(string $subnet): array {
        $i = $this->subnetInfo($subnet);
        return ['# Reseau invite - bridge isole (' . $i['cidr'] . ')', '/interface bridge', 'add name="bridge-guest"', '/ip address add address=' . $i['gateway'] . '/' . $i['mask'] . ' interface="bridge-guest"', '/ip pool add name="pool_guest" ranges=' . $i['start'] . '-' . $i['end'], '/ip dhcp-server add name="dhcp_guest" interface="bridge-guest" address-pool="pool_guest" disabled=no', '/ip dhcp-server network add address=' . $i['cidr'] . ' gateway=' . $i['gateway'] . ' dns-server=' . $i['gateway'], '/ip firewall filter add chain=forward action=drop src-address=' . $i['cidr'] . ' comment="Invite : isolation totale vers l\'interieur"', ''];
    }
    public function vlanStep(string $subnet): array {
        $i = $this->subnetInfo($subnet);
        return ['# VLAN 100 (gestion) sur bridge-lan', '/interface bridge set [find name="bridge-lan"] vlan-filtering=yes', '/interface vlan add name=vlan100 vlan-id=100 interface="bridge-lan"', '/ip address add address=' . $i['gateway'] . '/' . $i['mask'] . ' interface=vlan100', '/ip pool add name="pool_vlan100" ranges=' . $i['start'] . '-' . $i['end'], '/ip dhcp-server add name="dhcp_vlan100" interface=vlan100 address-pool="pool_vlan100" disabled=no', '/ip dhcp-server network add address=' . $i['cidr'] . ' gateway=' . $i['gateway'] . ' dns-server=' . $i['gateway'], ''];
    }
    public function firewallStep(string $profile): array {
        $out = ['# Pare-feu - profil ' . $profile . ' (deny by default)', '/ip firewall filter', 'add chain=input action=accept connection-state=established,related comment="Reponses"', 'add chain=input action=drop connection-state=invalid comment="Paquets invalides"'];
        if ($profile === 'Strict') { $out[] = 'add chain=input action=accept protocol=tcp dst-port=22,8291 in-interface-list=LAN comment="SSH/WinBox depuis le LAN uniquement"'; $out[] = 'add chain=input action=drop in-interface-list=LAN comment="Reste du LAN refuse"'; }
        else $out[] = 'add chain=input action=accept in-interface-list=LAN comment="LAN autorise"';
        $out[] = 'add chain=input action=drop in-interface-list=WAN comment="WAN refuse"';
        $out[] = 'add chain=forward action=accept connection-state=established,related comment="Transit existant"';
        $out[] = 'add chain=forward action=drop connection-state=invalid';
        $out[] = 'add chain=forward action=accept in-interface-list=LAN comment="LAN vers LAN"';
        $out[] = 'add chain=forward action=drop comment="Transit par defaut refuse"';
        $out[] = '';
        return $out;
    }
    public function natStep(): array { return ['# NAT (masquerade sortant)', '/ip firewall nat add chain=srcnat action=masquerade out-interface-list=WAN', '']; }
    public function ntpStep(): array { return ['/system ntp client set enabled=yes', '']; }
    private function guestBlock(): array { return $this->guestStep('10.10.10.0/24'); }
    private function vlanBlock(): array { return $this->vlanStep('10.100.100.0/24'); }
    private function firewallBlock(string $p): array { return $this->firewallStep($p); }
}
