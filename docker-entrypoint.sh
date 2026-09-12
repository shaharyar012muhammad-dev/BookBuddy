#!/bin/sh
set -e

PORT="${PORT:-8080}"
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

# ─── Debug: print actual env values ──────────────────────────────────────────
echo "==> MYSQLHOST=[${MYSQLHOST}] MYSQLPORT=[${MYSQLPORT}] MYSQLDATABASE=[${MYSQLDATABASE}]"

# ─── Wait for MySQL using TCP check ──────────────────────────────────────────
if [ -n "$MYSQLHOST" ] && [ "$MYSQLHOST" != "localhost" ]; then

    echo "==> Waiting for MySQL at ${MYSQLHOST}:${MYSQLPORT:-3306}..."

    MAX_TRIES=30
    TRIES=0

    while ! (echo > /dev/tcp/${MYSQLHOST}/${MYSQLPORT:-3306}) 2>/dev/null; do
        TRIES=$((TRIES + 1))
        if [ "$TRIES" -ge "$MAX_TRIES" ]; then
            echo "==> MySQL not ready after ${MAX_TRIES} attempts, skipping seed..."
            break
        fi
        echo "==> Attempt ${TRIES}/${MAX_TRIES} — retrying in 2s..."
        sleep 2
    done

    if [ "$TRIES" -lt "$MAX_TRIES" ]; then
        echo "==> MySQL TCP open! Running seed/migration..."
        php seed.php || echo "==> Seed non-zero, continuing..."
    fi

else
    echo "==> MYSQLHOST is empty or localhost — skipping seed (check Railway variables!)"
fi

# ─── Start PHP Server ─────────────────────────────────────────────────────────
echo "==> Starting server on 0.0.0.0:${PORT} with ${PHP_CLI_SERVER_WORKERS} workers..."
exec php -S "0.0.0.0:${PORT}" router.php
