#!/bin/sh
# CI minimale sans dépendance : lint PHP + tests CLI + presets.
# Usage : sh tests/ci.sh   (code retour 0 = succès)
set -e
PHP_BIN="${PHP:-}"
if [ -z "$PHP_BIN" ]; then
    if command -v php >/dev/null 2>&1; then
        PHP_BIN=$(command -v php)
    else
        # Défaut XAMPP pour ce dépôt.
        PHP_BIN=/Applications/XAMPP/xamppfiles/bin/php
    fi
fi
ROOT="$(dirname "$0")/.."
echo "== php -l =="
find "$ROOT/App" "$ROOT/config" "$ROOT/public" "$ROOT/tests" "$ROOT/bootstrap.php" "$ROOT/index.php" \
  -name '*.php' | while read -r f; do "$PHP_BIN" -l "$f" > /dev/null || exit 1; done
echo "lint OK"
echo "== tests =="
"$PHP_BIN" "$ROOT/tests/run_tests.php"
echo "== presets =="
"$PHP_BIN" "$ROOT/tests/check_presets.php"
echo "CI OK"
