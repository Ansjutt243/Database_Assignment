#!/bin/zsh
# =====================================================================
# ML IT Helpdesk - one-time database setup
# Builds the mlhelpdesk database from scratch: tables, views, triggers,
# procedures, pilot data and security roles, then runs the 38 tests.
# WARNING: deletes any existing mlhelpdesk database.
# Usage:  ./setup.sh
# =====================================================================
set -e
cd "$(dirname "$0")/database"
MYSQL="${MYSQL_BIN:-$HOME/mysql/bin/mysql}"

if ! "$HOME/mysql/bin/mysqladmin" --socket=/tmp/mysql.sock ping >/dev/null 2>&1; then
  echo "MySQL is not running. Start it first with ./start.sh"
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
