<?php
/**
 * Journalisation fichier — Générateur MikroTik V2.
 * Pourquoi : tracer les 400/500 sans exposer les internals au client.
 * Chaque entrée porte un ID de corrélation (date + aléatoire) repris
 * dans la réponse 500 en production.
 */
declare(strict_types=1);

namespace App\Services;

class Logger
{
    public function __construct(
        private string $file,
        private bool $debug = false
    ) {}

    /** Écrit une ligne INFO et retourne l'ID de corrélation. */
    public function info(string $message, array $context = []): string
    {
        return $this->write('INFO', $message, $context);
    }

    /** Écrit une ligne ERROR et retourne l'ID de corrélation. */
    public function error(string $message, array $context = []): string
    {
        return $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): string
    {
        $id = gmdate('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $line = sprintf(
            "[%s] %s %s: %s %s\n",
            gmdate('c'),
            $id,
            $level,
            $message,
            $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
        return $id;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }
}
