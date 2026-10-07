<?php
declare(strict_types=1);
namespace App\Services;
/** Moteur de templates : include fichier reel, aucun eval(). */
class TemplateEngine {
    public function render(string $template, array $vars = []): string {
        if (!is_file($template)) throw new \InvalidArgumentException('Template introuvable : ' . $template);
        extract($vars, EXTR_SKIP);
        ob_start();
        try { include $template; } catch (\Throwable $e) { ob_end_clean(); throw $e; }
        return (string) ob_get_clean();
    }
}
