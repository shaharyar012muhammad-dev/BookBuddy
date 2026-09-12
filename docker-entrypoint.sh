#!/bin/sh
set -e

PORT="${PORT:-8080}"
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

echo "==> MYSQLHOST=[${MYSQLHOST}] MYSQLPORT=[${MYSQLPORT}] MYSQLDATABASE=[${MYSQLDATABASE}]"

# ─── Wait for MySQL using actual PDO connection ───────────────────────────────
if [ -n "$MYSQLHOST" ] && [ "$MYSQLHOST" != "localhost" ]; then

    echo "==> Waiting for MySQL to accept connections..."

    MAX_TRIES=30
    TRIES=0
    MYSQL_READY=0

    while [ $TRIES -lt $MAX_TRIES ]; do
        TRIES=$((TRIES + 1))

        RESULT=$(php -r "
            try {
                \$dsn = 'mysql:host=' . getenv('MYSQLHOST') . ';port=' . (getenv('MYSQLPORT') ?: '3306');
                \$pdo = new PDO(\$dsn, getenv('MYSQLUSER'), getenv('MYSQLPASSWORD'));
                echo 'ok';
            } catch (Exception \$e) {
                echo 'fail:' . \$e->getMessage();
            }
        " 2>/dev/null)

        if [ "$RESULT" = "ok" ]; then
            MYSQL_READY=1
            echo "==> MySQL ready after ${TRIES} attempt(s)!"
            break
        fi

        echo "==> Attempt ${TRIES}/${MAX_TRIES} — ${RESULT} — retrying in 2s..."
        sleep 2
    done

    if [ $MYSQL_READY -eq 1 ]; then
        echo "==> Running seed/migration..."
        php seed.php || echo "==> Seed non-zero, continuing..."
    else
        echo "==> MySQL not ready after ${MAX_TRIES} attempts, skipping seed..."
    fi

else
    echo "==> MYSQLHOST empty or localhost — skipping seed"
fi

# ─── Start PHP Server ─────────────────────────────────────────────────────────
echo "==> Starting server on 0.0.0.0:${PORT} with ${PHP_CLI_SERVER_WORKERS} workers..."
exec php -S "0.0.0.0:${PORT}" router.php
