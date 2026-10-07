<?php
/**
 * Point d'entrée HTTP Nginx — docroot = public/ (code hors docroot).
 * Usage Proxmox/Nginx : root .../mikrotik2/public ; voir nginx.conf.example.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Controllers\GeneratorController;

(new GeneratorController())->handle();
