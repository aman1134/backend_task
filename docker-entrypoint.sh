#!/bin/bash
set -e

# Default environment variables if not set
DB_HOST=${DB_HOST:-db}
DB_USER=${DB_USER:-debricked}
DB_PASSWORD=${DB_PASSWORD:-debricked}
DB_NAME=${DB_NAME:-debricked}

# Function to check if MySQL is up
check_mysql() {
  mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASSWORD" --execute="USE $DB_NAME;"
}

# Wait for MySQL to be ready
echo "Waiting for the database to be ready..."
until check_mysql; do
  echo "Database not ready, waiting..."
  sleep 5
done

echo "Database is ready. Running migrations..."

LOCK_FILE="/tmp/migration_command.lock"
if [ ! -f "$LOCK_FILE" ]; then
# Run Doctrine migrations
  yes |  php bin/console doctrine:schema:update --force
  touch "$LOCK_FILE"
fi

# Execute the default command
exec "$@"
