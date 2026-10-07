<?php
/**
 * Vue unique orchestree en partiels (Lot 4) : head / form / output.
 * window.APP_DEFAULTS alimente l'etat initial ; le script n'est JAMAIS
 * genere cote client : le front poste le JSON et affiche la reponse serveur.
 * Compatible racine (XAMPP) et docroot public/ (Nginx) via $baseUrl.
 */
$appConfig = is_file(dirname(__DIR__, 2) . '/config/app.php') ? (require dirname(__DIR__, 2) . '/config/app.php') : [];
$appVersion = is_array($appConfig) ? (string)($appConfig['version'] ?? '2.2.0') : '2.2.0';
$identityDefault = 'MikroTik-Router';
$baseUrl = ('' !== ($baseUrl ?? '')) ? rtrim((string)$baseUrl, '/') : '';
$prefix = $baseUrl !== '' ? $baseUrl . '/' : '';
$cssUrl = $prefix . 'assets/css/style.css?v=' . rawurlencode($appVersion);
$jsUrl = $prefix . 'assets/js/app.js?v=' . rawurlencode($appVersion);
$title = 'RouterOS Generator V2';
$json = json_encode([
  'identity' => $identityDefault,
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
  'guest_subnet' => '10.10.10.0/24', 'vlan_subnet' => '10.100.100.0/24',
  'ntp' => false, 'nat' => true, 'guest' => false, 'vlan' => false, 'firewall' => true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$dir = __DIR__ . '/partials';
require $dir . '/head.php';
require $dir . '/form.php';
require $dir . '/output.php';
