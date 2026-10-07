<?php
/**
 * Configuration centrale — Générateur MikroTik V2.
 * Aucun secret ici. APP_DEBUG via variable d'environnement (défaut : false = production).
 */
declare(strict_types=1);

$debugEnv = getenv('APP_DEBUG');
$debug = $debugEnv !== false
    ? in_array(strtolower((string) $debugEnv), ['1', 'true', 'yes', 'on'], true)
    : false;

return [
    'version'   => '2.2.0',
    'debug'     => $debug, // true = messages détaillés (dev), false = message générique (prod)
    'max_body'  => 262144, // 256 Ko — borne anti-DoS mémoire
    'rate_limit' => [
        'max_requests' => 30, // 30 POST / minute / IP (salle de TP)
        'window'       => 60,
    ],
    'log_file' => dirname(__DIR__) . '/storage/logs/app.log',
    'ratelimit_dir' => dirname(__DIR__) . '/storage/ratelimit',
    // Sous-réseaux réservés par les blocs optionnels (paramétrables côté formulaire).
    'reserved' => [
        'guest_default' => '10.10.10.0/24',
        'vlan_default'  => '10.100.100.0/24',
    ],
];
