<?php
declare(strict_types=1);
// Autoloader natif PSR-4 minimal (sans Composer) : App\ -> App/
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $rel = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    // Correspondance singulier historique -> pluriel reel (BC Linux/Proxmox)
    $candidates = [__DIR__ . '/App/' . $rel];
    $candidates[] = __DIR__ . '/App/' . str_replace(['Controllers/','Services/','Views/'], ['controller/','Service/','View/'], $rel);
    foreach ($candidates as $f) { if (is_file($f)) { require_once $f; return; } }
});
// Alias BC : ancien namespace singulier -> nouveau pluriel
foreach ([['App\\Service\\ValidationService','App\\Services\\ValidationService'],['App\\Service\\RouterOSGenerator','App\\Services\\RouterOSGenerator'],['App\\Service\\TemplateEngine','App\\Services\\TemplateEngine'],['App\\Service\\IpUtils','App\\Services\\IpUtils'],['App\\Service\\Logger','App\\Services\\Logger'],['App\\Service\\RateLimiter','App\\Services\\RateLimiter'],['App\\Controller\\GeneratorController','App\\Controllers\\GeneratorController']] as [$old,$new]) {
    if (!class_exists($old) && class_exists($new)) class_alias($new, $old);
}
