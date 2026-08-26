#!/usr/bin/env bash
# Per-boot startup: bring up the local MariaDB service and provision the dev DB.
# Idempotent: safe to run on every environment start.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

sudo mkdir -p /var/run/mysqld
sudo chown mysql:mysql /var/run/mysqld

# Initialize the data directory the first time (fresh image / no snapshot).
if [ ! -d /var/lib/mysql/mysql ]; then
  sudo mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null 2>&1 || true
fi

# Start mariadbd only if it is not already running.
if ! sudo mariadb -e "SELECT 1" >/dev/null 2>&1; then
  sudo mariadbd-safe --datadir=/var/lib/mysql >/tmp/mariadb.log 2>&1 &
  for _ in $(seq 1 30); do
    if sudo mariadb -e "SELECT 1" >/dev/null 2>&1; then
      break
    fi
    sleep 1
  done
fi

# Provision the development schema (idempotent).
sudo mariadb < "${SCRIPT_DIR}/provision-db.sql"

echo "MariaDB is up; suplidores_dev provisioned."
