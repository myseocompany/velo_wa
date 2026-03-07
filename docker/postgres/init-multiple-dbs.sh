#!/bin/bash
# Creates extra databases listed in POSTGRES_MULTIPLE_DATABASES (comma-separated).
# Runs only on first boot (initdb). The primary POSTGRES_DB is already created by Postgres itself.
set -e

if [ -z "$POSTGRES_MULTIPLE_DATABASES" ]; then
  echo "POSTGRES_MULTIPLE_DATABASES not set — skipping extra DB creation"
  exit 0
fi

for db in $(echo "$POSTGRES_MULTIPLE_DATABASES" | tr ',' ' '); do
  echo "Creating database: $db"
  psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    SELECT 'CREATE DATABASE "$db"'
    WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '$db')\gexec
    GRANT ALL PRIVILEGES ON DATABASE "$db" TO "$POSTGRES_USER";
EOSQL
done
