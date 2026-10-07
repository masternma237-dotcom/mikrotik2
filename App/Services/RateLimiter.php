<?php
/**
 * Rate-limit natif fichier — 30 POST/min/IP par défaut.
 * Pourquoi : anti-DoS salle de TP sans dépendance (Redis/APCu).
 * Stockage : storage/ratelimit/<hash-ip>.json — fenêtre glissante.
 */
declare(strict_types=1);

namespace App\Services;

class RateLimiter
{
    public function __construct(
        private string $dir,
        private int $maxRequests = 30,
        private int $window = 60
    ) {}

    /** Retourne true si la requête est autorisée, false si quota dépassé. */
    public function allow(string $ip): bool
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
        $file = $this->dir . '/' . preg_replace('/[^a-f0-9]/', '', hash('sha256', $ip)) . '.json';
        $now = time();
        $hits = [];
        if (is_file($file)) {
            $decoded = json_decode((string) @file_get_contents($file), true);
            if (is_array($decoded)) {
                $hits = array_values(array_filter($decoded, fn($t) => is_int($t) && $t > $now - $this->window));
            }
        }
        if (count($hits) >= $this->maxRequests) {
            @file_put_contents($file, json_encode($hits), LOCK_EX);
            return false;
        }
        $hits[] = $now;
        @file_put_contents($file, json_encode($hits), LOCK_EX);
        return true;
    }
}
