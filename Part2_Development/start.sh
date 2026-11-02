#!/bin/zsh
# =====================================================================
# ML IT Helpdesk - start the system on this computer (macOS / Linux)
#   1. checks MySQL is running (starts the portable ~/mysql if needed)
#   2. starts the PHP web server on http://127.0.0.1:8080
# Stop with Ctrl+C (MySQL keeps running; stop it with ./stop.sh).
# Windows: start.bat
# =====================================================================
cd "$(dirname "$0")"
source deploy/find_tools.sh

if [[ -z "$PHP" ]]; then
  echo "PHP was not found. Install PHP 8.2 or newer (see docs/01_Setup_Guide.md) or set PHP_BIN."
  exit 1
fi

if "$MYSQLADMIN" ping >/dev/null 2>&1; then
  echo "MySQL is already running."
elif [[ -x "$HOME/mysql/bin/mysqld_safe" && -f "$HOME/mysql/my.cnf" ]]; then
  echo "Starting MySQL ..."
  "$HOME/mysql/bin/mysqld_safe" --defaults-file="$HOME/mysql/my.cnf" > /dev/null 2>&1 &
  for i in {1..30}; do
    "$MYSQLADMIN" ping >/dev/null 2>&1 && break
    sleep 1
  done
  echo "MySQL started."
else
  echo "MySQL is not running. Start it first:"
  echo "  MySQL installer (.dmg): System Settings -> MySQL -> Start MySQL Server"
  echo "  Homebrew:               brew services start mysql@8.4"
  exit 1
fi

echo "ML IT Helpdesk is running at  http://127.0.0.1:8080   (Ctrl+C to stop)"
exec "$PHP" -S 127.0.0.1:8080 -t app/public
