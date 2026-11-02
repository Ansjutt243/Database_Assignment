#!/bin/zsh
# =====================================================================
# ML IT Helpdesk - one-time database setup
# Builds the mlhelpdesk database from scratch: tables, views, triggers,
# procedures, pilot data and security roles, then runs the 41 tests.
# WARNING: deletes any existing mlhelpdesk database.
# Usage:  ./setup.sh          (Windows: setup.bat)
# =====================================================================
set -e
cd "$(dirname "$0")"
source deploy/find_tools.sh
cd database

if ! "$MYSQLADMIN" ping >/dev/null 2>&1; then
  echo "MySQL is not running. Start it first (./start.sh starts the portable one)."
  exit 1
fi

echo -n "MySQL root password: "
read -s ROOTPW; echo
export MYSQL_PWD="$ROOTPW"

echo "Running 01_create_tables.sql ..."            # creates the database itself
"$MYSQL" -uroot < 01_create_tables.sql > /dev/null
for f in 02_views_triggers_procedures.sql 03_pilot_data.sql 04_security_roles.sql; do
  echo "Running $f ..."
  "$MYSQL" -uroot mlhelpdesk < "$f" > /dev/null
done

echo "Running 06_validation_tests.sql ..."
"$MYSQL" -uroot -t mlhelpdesk < 06_validation_tests.sql | tail -5
unset MYSQL_PWD
echo "Done. Start the application with ./start.sh"
