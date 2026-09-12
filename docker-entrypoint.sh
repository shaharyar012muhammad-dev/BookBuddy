#!/bin/sh
set -e

PORT="${PORT:-8080}"
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

# ─── Wait for MySQL to be ready ───────────────────────────────────────────────
if [ -n "$MYSQLHOST" ] || [ -n "$MYSQL_URL" ] || [ -n "$DATABASE_URL" ]; then

    echo "==> Waiting for MySQL to be ready..."

    MAX_TRIES=30
    TRIES=0

    while ! php -r "
        try {
            \$host = getenv('MYSQLHOST') ?: 'localhost';
            \$port = getenv('MYSQLPORT') ?: '3306';
            \$user = getenv('MYSQLUSER') ?: 'root';
            \$pass = getenv('MYSQLPASSWORD') ?: '';
            new PDO(\"mysql:host=\$host;port=\$port\", \$user, \$pass);
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; do
        TRIES=$((TRIES + 1))
        if [ "$TRIES" -ge "$MAX_TRIES" ]; then
            echo "==> MySQL not ready after ${MAX_TRIES} attempts, skipping seed..."
            break
        fi
        echo "==> MySQL not ready yet (attempt ${TRIES}/${MAX_TRIES}), retrying in 2s..."
        sleep 2
    done

    if [ "$TRIES" -lt "$MAX_TRIES" ]; then
        echo "==> MySQL is ready! Running seed/migration..."
        php seed.php || echo "==> Seed returned non-zero, continuing..."
    fi

fi

# ─── Start PHP Server ─────────────────────────────────────────────────────────
echo "==> Starting server on 0.0.0.0:${PORT} with ${PHP_CLI_SERVER_WORKERS} workers..."
exec php -S "0.0.0.0:${PORT}" router.php
