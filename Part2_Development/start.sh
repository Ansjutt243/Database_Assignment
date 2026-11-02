#!/bin/zsh
# =====================================================================
# ML IT Helpdesk - start the system on this computer
#   1. starts MySQL (if it is not already running)
#   2. starts the PHP web server on http://127.0.0.1:8080
# Stop with Ctrl+C (MySQL keeps running; stop it with ./stop.sh).
# =====================================================================
cd "$(dirname "$0")"
MYSQL_HOME_DIR="$HOME/mysql"
PHP="${PHP_BIN:-$HOME/php/php}"

if "$MYSQL_HOME_DIR/bin/mysqladmin" --socket=/tmp/mysql.sock ping >/dev/null 2>&1; then
  echo "MySQL is already running."
else
  echo "Starting MySQL ..."
  "$MYSQL_HOME_DIR/bin/mysqld_safe" --defaults-file="$MYSQL_HOME_DIR/my.cnf" > /dev/null 2>&1 &
  for i in {1..30}; do
    "$MYSQL_HOME_DIR/bin/mysqladmin" --socket=/tmp/mysql.sock ping >/dev/null 2>&1 && break
    sleep 1
  done
  echo "MySQL started."
fi

echo "ML IT Helpdesk is running at  http://127.0.0.1:8080   (Ctrl+C to stop)"
exec "$PHP" -S 127.0.0.1:8080 -t app/public
