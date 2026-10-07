<?php
/**
 * Front controller — exécution unique des couches de l'application (require_once,
 * chemins en minuscules cohérents avec l'arborescence réelle pour rester portable
 * sous Linux/Proxmox, où le système de fichiers est sensible à la casse).
 */
require_once __DIR__ . '/App/controller/GeneratorController.php';
require_once __DIR__ . '/App/Service/ValidationService.php';
require_once __DIR__ . '/App/Service/RouterOSGenerator.php';
require_once __DIR__ . '/App/Service/TemplateEngine.php';

use App\Controller\GeneratorController;

$controller = new GeneratorController();
$controller->handle();