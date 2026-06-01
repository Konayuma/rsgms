#!/bin/bash
set -e

# Wait for MySQL to be available
if [ -n "$DB_HOST" ]; then
    echo "Waiting for MySQL at $DB_HOST:$DB_PORT..."
    until php -r "
        try {
            new PDO('mysql:host=$DB_HOST;port=$DB_PORT;charset=utf8mb4', '$DB_USER', '$DB_PASSWORD');
            echo 'ok';
        } catch (PDOException \$e) {
            echo \$e->getMessage();
            exit(1);
        }
    " 2>/dev/null | grep -q 'ok'; do
        sleep 2
    done
    echo "MySQL is ready."
fi

# Seed test data if admin user doesn't exist
if [ -n "$DB_HOST" ] && [ -n "$DB_NAME" ] && [ -n "$DB_USER" ] && [ -n "$DB_PASSWORD" ]; then
    echo "Checking if seed data is needed..."
    ADMIN_EXISTS=$(php -r "
        try {
            \$pdo = new PDO('mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4', '$DB_USER', '$DB_PASSWORD');
            \$stmt = \$pdo->query('SELECT COUNT(*) FROM users WHERE username = \\'admin\\'');
            echo \$stmt->fetchColumn();
        } catch (PDOException \$e) {
            echo '0';
        }
    " 2>/dev/null)

    if [ "$ADMIN_EXISTS" = "0" ]; then
        echo "Seeding database..."
        php /var/www/html/seed_test_data.php
    else
        echo "Database already seeded."
    fi
fi

exec apache2-foreground
