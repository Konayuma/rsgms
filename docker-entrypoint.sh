#!/bin/bash
set -e

CHECK_SCRIPT="/var/www/html/scripts/check_db.php"

if [ -n "$DB_HOST" ]; then
    echo "Waiting for MySQL at $DB_HOST:$DB_PORT..."
    until php "$CHECK_SCRIPT" ping 2>/dev/null | grep -q 'ok'; do
        sleep 2
    done
    echo "MySQL is ready."
fi

if [ -n "$DB_HOST" ] && [ -n "$DB_NAME" ]; then
    echo "Checking if seed data is needed..."
    ADMIN_COUNT=$(php "$CHECK_SCRIPT" check_seed 2>/dev/null)
    if [ "$ADMIN_COUNT" = "0" ]; then
        echo "Seeding database..."
        php /var/www/html/seed_test_data.php
    else
        echo "Database already seeded."
    fi
fi

exec apache2-foreground
