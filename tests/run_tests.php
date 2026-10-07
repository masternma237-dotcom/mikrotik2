<?php
/**
 * Tests ciblés (CLI) : ValidationService + RouterOSGenerator + TemplateEngine.
 * Lancement : php tests/run_tests.php  (code retour 0 = succès, 1 = échec)
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../App/Services/IpUtils.php';
require_once __DIR__ . '/../App/Services/ValidationService.php';
require_once __DIR__ . '/../App/Services/RouterOSGenerator.php';
require_once __DIR__ . '/../App/Services/TemplateEngine.php';
require_once __DIR__ . '/../App/Services/Logger.php';
require_once __DIR__ . '/../App/Services/RateLimiter.php';

use App\Services\ValidationService;
use App\Services\RouterOSGenerator;
use App\Services\TemplateEngine;
use App\Services\IpUtils;
use App\Services\RateLimiter;

$pass = 0;
$fail = 0;
$failures = [];

function check(string $name, bool $ok, string $detail = ''): void {
    global $pass, $fail, $failures;
    if ($ok) {
        $pass++;
        echo "  [OK]   $name\n";
    } else {
        $fail++;
        $failures[] = $name . ($detail !== '' ? " — $detail" : '');
        echo "  [FAIL] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

/** Payload complet et valide, sur lequel on branche les variations. */
function payload(array $overrides = []): array {
    $base = [
        'identity' => 'R-Test',
        'security_profile' => 'Home',
        'bridges' => [['name' => 'bridge-lan', 'ports' => ['ether2', 'ether3']]],
        'wifi' => [[
            'iface' => 'wifi1', 'ssid' => 'MikroTik-24',
            'security' => 'WPA2-Personal', 'password' => 'change-me-123',
        ]],
        'networks' => [[
            'interface' => 'bridge-lan', 'address' => '192.168.88.1', 'mask' => 24,
            'gateway' => '192.168.88.1', 'dns' => '192.168.88.1', 'dhcp' => true,
            'range_start' => '192.168.88.10', 'range_end' => '192.168.88.254',
        ]],
        'ntp' => false, 'nat' => true, 'guest' => false, 'vlan' => false, 'firewall' => true,
    ];
    return array_replace_recursive($base, $overrides);
}

echo "\n== ValidationService ==\n";
$v = new ValidationService();

$errors = $v->validate(payload(['identity' => '   ']));
check('identité vide refusée', isset($errors['identity']));

$errors = $v->validate(payload(['wifi' => [['iface' => 'wifi1', 'ssid' => 'S', 'security' => 'WPA2-Personal', 'password' => 'court']]]));
check('mot de passe Wi-Fi < 8 caractères refusé', isset($errors['wifi.0.password']));

$errors = $v->validate(payload(['networks' => [['interface' => 'lan', 'address' => '999.1.1.1', 'mask' => 24, 'dhcp' => false]]]));
check('adresse IP invalide refusée', isset($errors['networks.0.address']));

$errors = $v->validate(payload(['security_profile' => 'DANGER']));
check('profil de sécurité inconnu refusé', isset($errors['security_profile']));

$errors = $v->validate(payload());
check('payload de référence accepté', $errors === [], json_encode($errors, JSON_UNESCAPED_UNICODE));

echo "\n== RouterOSGenerator ==\n";
$gen = new RouterOSGenerator(new TemplateEngine());
$script = $gen->generate(payload());

check('identité posée', str_contains($script, '/system identity set name="R-Test"'));
check('bridges + ports', str_contains($script, 'add name="bridge-lan"') && str_contains($script, 'interface=ether2'));
check('sécurité Wi-Fi WPA2 émise (passphrase)', str_contains($script, 'authentication-types=wpa2-psk') && str_contains($script, 'passphrase="change-me-123"'));
check('interface Wi-Fi référencée', str_contains($script, 'security="sec_wifi1"'));
check('DHCP + réseau calculé', str_contains($script, '/ip dhcp-server network add address=192.168.88.0/24'));
check('NAT masquerade', str_contains($script, 'action=masquerade'));
check('pare-feu Home (LAN autorisé)', str_contains($script, 'LAN autorise') && str_contains($script, 'chain=input action=drop in-interface-list=WAN'));

// Masque /20 : l'ancienne implémentation produisait 192.168.0.0 (mask /16)
$script20 = $gen->generate(payload([
    'networks' => [[
        'interface' => 'bridge-lan', 'address' => '192.168.20.5', 'mask' => 20,
        'gateway' => '192.168.20.1', 'dns' => '192.168.20.1', 'dhcp' => true,
        'range_start' => '192.168.20.10', 'range_end' => '192.168.20.200',
    ]],
]));
check('sous-réseau /20 exact (192.168.16.0)', str_contains($script20, 'address=192.168.16.0/20'));

// Masque dépointé accepté
$scriptMask = $gen->generate(payload([
    'networks' => [[
        'interface' => 'bridge-lan', 'address' => '10.20.30.40', 'mask' => '255.255.240.0',
        'dhcp' => false,
    ]],
]));
check('masque dépointé 255.255.240.0 converti en /20', str_contains($scriptMask, 'address=10.20.30.40/20'));

// Ancien pilote wireless
$scriptLegacy = $gen->generate(payload(['wifi' => [[
    'iface' => 'wlan1', 'ssid' => 'Ancien', 'security' => 'WPA3-Personal', 'password' => 'motdepasse1',
]]]));
check('ancien pilote : security-profile + wpa3-sae', str_contains($scriptLegacy, 'security-profile="sec_wlan1"') && str_contains($scriptLegacy, 'authentication-types=wpa3-sae'));

// Options
$scriptOpts = $gen->generate(payload(['guest' => true, 'vlan' => true, 'ntp' => true]));
check('réseau invité généré (bridge-guest + isolation)', str_contains($scriptOpts, 'bridge-guest') && str_contains($scriptOpts, 'src-address=10.10.10.0/24'));
check('VLAN 100 généré', str_contains($scriptOpts, 'vlan-id=100') && str_contains($scriptOpts, 'address=10.100.100.1/24'));
check('NTP client activé', str_contains($scriptOpts, '/system ntp client set enabled=yes'));

// Profil Strict
$scriptStrict = $gen->generate(payload(['security_profile' => 'Strict']));
check('profil Strict : seuls SSH/WinBox ouverts depuis le LAN',
    str_contains($scriptStrict, 'dst-port=22,8291') && str_contains($scriptStrict, 'Reste du LAN refuse'));

// Échappement RouterOS
$scriptEsc = $gen->generate(payload(['identity' => 'evil"name']));
check('guillemet échappé dans la sortie RouterOS', str_contains($scriptEsc, 'name="evil\\"name"'));

// Injection d'interface neutralisée : plus aucun séparateur de commande ni
// référence d'origine (guillemet, point-virgule, espace, barre oblique).
$scriptInject = $gen->generate(payload(['wifi' => [[
    'iface' => 'wifi1"; /system reset-configuration', 'ssid' => 'S', 'security' => 'WPA2-Personal', 'password' => 'motdepasse1',
]]]));
check('interface malicieuse : séquence d\'injection détruite',
    !str_contains($scriptInject, 'wifi1";')
    && !str_contains($scriptInject, '/system reset-configuration')
    && !str_contains($scriptInject, 'wifi1"; /system'));
check('interface malicieuse : nom réduit au jeu autorisé',
    str_contains($scriptInject, 'default-name=wifi1systemreset-configuration'));

echo "\n== Validation etendue (Lot 2) ==\n";
$errors = $v->validate(payload(['networks' => [['interface'=>'bridge-lan','address'=>'192.168.88.1','mask'=>24,'gateway'=>'192.168.99.1','dns'=>'192.168.88.1','dhcp'=>false]]]));
check('passerelle hors sous-reseau refusee', isset($errors['networks.0.gateway']));
$errors = $v->validate(payload(['networks' => [['interface'=>'bridge-lan','address'=>'192.168.88.1','mask'=>24,'gateway'=>'192.168.88.1','dns'=>'not-an-ip','dhcp'=>false]]]));
check('DNS invalide refuse', isset($errors['networks.0.dns']));
$errors = $v->validate(payload(['networks' => [['interface'=>'bridge-lan','address'=>'192.168.88.1','mask'=>24,'gateway'=>'192.168.88.1','dns'=>'192.168.88.1','dhcp'=>true,'range_start'=>'192.168.88.200','range_end'=>'192.168.88.10']]]));
check('plage DHCP inversee refusee', isset($errors['networks.0.range']));
$errors = $v->validate(payload(['networks' => [['interface'=>'bridge-lan','address'=>'192.168.88.1','mask'=>24,'gateway'=>'192.168.88.1','dns'=>'192.168.88.1','dhcp'=>true,'range_start'=>'192.168.99.10','range_end'=>'192.168.99.20']]]));
check('plage DHCP hors sous-reseau refusee', isset($errors['networks.0.range']));
$errors = $v->validate(payload(['networks' => [['interface'=>'bridge-lan','address'=>'192.168.88.1','mask'=>33,'dhcp'=>false]]]));
check('masque 33 refuse', isset($errors['networks.0.mask']));
$errors = $v->validate(payload(['networks' => [['interface'=>'bridge-lan','address'=>'192.168.88.1','mask'=>'255.0.255.0','dhcp'=>false]]]));
check('masque non contigu refuse', isset($errors['networks.0.mask']));
$errors = $v->validate(payload(['bridges' => [['name'=>'bridge-lan','ports'=>['ether2']],['name'=>'BRIDGE-LAN','ports'=>['ether3']]]]));
check('nom de bridge en double refuse', isset($errors['bridges.1.name']));
$errors = $v->validate(payload(['bridges' => [['name'=>'bridge-lan','ports'=>['ether2']],['name'=>'bridge-2','ports'=>['ether2']]]]));
check('port assigne deux fois refuse', isset($errors['bridges.1.ports']));
$errors = $v->validate(payload(['guest' => true, 'networks' => [['interface'=>'bridge-lan','address'=>'10.10.10.5','mask'=>24,'gateway'=>'10.10.10.1','dns'=>'10.10.10.1','dhcp'=>false]]]));
check('collision reseau/invite detectee', isset($errors['networks.0.address']));
$errors = $v->validate(payload(['vlan' => true, 'networks' => [['interface'=>'bridge-lan','address'=>'10.100.100.5','mask'=>24,'gateway'=>'10.100.100.1','dns'=>'10.100.100.1','dhcp'=>false]]]));
check('collision reseau/VLAN detectee', isset($errors['networks.0.address']));
$errors = $v->validate(payload(['guest' => true, 'vlan' => true, 'guest_subnet' => '10.10.10.0/24', 'vlan_subnet' => '10.10.10.0/24']));
check('chevauchement guest/VLAN refuse', isset($errors['vlan_subnet']));
$errors = $v->validate(payload(['guest' => true, 'guest_subnet' => 'pas-un-cidr']));
check('sous-reseau invite invalide refuse', isset($errors['guest_subnet']));
$secondNet = payload(['networks' => [
  ['interface'=>'bridge-lan','address'=>'192.168.88.1','mask'=>24,'gateway'=>'192.168.88.1','dns'=>'192.168.88.1','dhcp'=>false],
  ['interface'=>'bridge-2','address'=>'192.168.88.200','mask'=>24,'gateway'=>'192.168.88.1','dns'=>'192.168.88.1','dhcp'=>false],
]]);
$errors = $v->validate($secondNet);
check('chevauchement entre reseaux utilisateurs refuse', isset($errors['networks.1.address']));
check('IpUtils toCidr 255.255.255.0 => 24', IpUtils::toCidr('255.255.255.0') === 24);
check('IpUtils overlaps /24 vs /25 interne', IpUtils::overlaps('192.168.1.0', 24, '192.168.1.128', 25));
check('IpUtils pas de chevauchement /24 distincts', !IpUtils::overlaps('192.168.1.0', 24, '192.168.2.0', 24));
check('IpUtils inSubnet passerelle OK', IpUtils::inSubnet('192.168.88.1', '192.168.88.10', 24));
$rlDir = sys_get_temp_dir() . '/mikrotik2-test-' . getmypid();
@mkdir($rlDir, 0775, true);
$rl = new RateLimiter($rlDir, 3, 60);
$okRl = $rl->allow('127.0.0.1') && $rl->allow('127.0.0.1') && $rl->allow('127.0.0.1') && !$rl->allow('127.0.0.1');
check('rate-limit bloque au-dela du quota', $okRl);
foreach (glob($rlDir . '/*.json') ?: [] as $f) { @unlink($f); }
@rmdir($rlDir);
$guestScript = $gen->generate(payload(['guest' => true, 'guest_subnet' => '10.20.0.0/24']));
check('guest parametrable 10.20.0.0/24', str_contains($guestScript, '10.20.0.0/24') && str_contains($guestScript, '10.20.0.1/24'));
$vlanScript = $gen->generate(payload(['vlan' => true, 'vlan_subnet' => '10.30.0.0/24']));
check('VLAN parametrable 10.30.0.0/24', str_contains($vlanScript, '10.30.0.0/24'));

echo "\n== TemplateEngine ==\n";
$te = new TemplateEngine();
$out = $te->render(__DIR__ . '/fixtures/template.php', ['name' => 'MikroTik']);
check('rendu du template de fixture', trim($out) === 'Bonjour MikroTik', var_export($out, true));

$threw = false;
try {
    $te->render(__DIR__ . '/fixtures/inexistant.php', []);
} catch (InvalidArgumentException $e) {
    $threw = true;
}
check('template manquant -> exception explicite', $threw);

echo "\n----------------------------------------\n";
echo "Reussis : $pass  |  Echoues : $fail\n";
if ($fail > 0) {
    foreach ($failures as $f) {
        echo "  - $f\n";
    }
    exit(1);
}
echo "Tous les tests passent.\n";
exit(0);
