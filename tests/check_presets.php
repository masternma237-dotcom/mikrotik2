<?php
declare(strict_types=1);
// Lint minimal des fichiers presets/*.json (Lot 5).
$dir = __DIR__ . '/../presets';
$ok = true;
foreach (glob($dir . '/*.json') ?: [] as $f) {
    $raw = file_get_contents($f);
    if ($raw === false || json_decode($raw, true) === null) {
        echo "[FAIL] $f\n";
        $ok = false;
    } else {
        echo "[OK]   $f\n";
    }
}
exit($ok ? 0 : 1);
