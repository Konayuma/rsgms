#!/bin/bash
set -e

PORT=${PORT:-80}
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/:80>/:$PORT>/g" /etc/apache2/sites-available/000-default.conf
echo "Apache configured to listen on port $PORT"

CHECK_SCRIPT="/var/www/html/scripts/check_db.php"

if [ -n "$DB_HOST" ] && [ -n "$DB_PORT" ]; then
    echo "Waiting for MySQL at $DB_HOST:$DB_PORT..."
    WAIT=0
    until php "$CHECK_SCRIPT" ping 2>/dev/null | grep -q 'ok'; do
        sleep 2
        WAIT=$((WAIT + 2))
        if [ $WAIT -ge 60 ]; then
            echo "WARNING: MySQL not reachable after 60s, starting Apache anyway."
            break
        fi
    done
    if php "$CHECK_SCRIPT" ping 2>/dev/null | grep -q 'ok'; then
        echo "MySQL is ready."
        echo "Checking if seed data is needed..."
        ADMIN_COUNT=$(php "$CHECK_SCRIPT" check_seed 2>/dev/null)
        if [ "$ADMIN_COUNT" = "0" ]; then
            echo "Seeding database..."
            php /var/www/html/seed_test_data.php
        else
            echo "Database already seeded."
        fi
    fi
fi

exec apache2-foreground
