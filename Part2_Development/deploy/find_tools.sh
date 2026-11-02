# =====================================================================
# Shared by setup.sh, start.sh, stop.sh and install_schedule.sh (macOS / Linux).
# Finds MySQL and PHP wherever they were installed:
#   ~/mysql, ~/php                      portable install (development computer)
#   /usr/local/mysql                    MySQL installer from dev.mysql.com (.dmg)
#   /opt/homebrew, /usr/local, PATH     Homebrew or Linux packages
# Override with MYSQL_BIN_DIR=/path/to/mysql/bin or PHP_BIN=/path/to/php
# =====================================================================
find_mysql_dir() {
  local d
  for d in "$MYSQL_BIN_DIR" "$HOME/mysql/bin" /usr/local/mysql/bin /opt/homebrew/opt/mysql@8.4/bin \
           /usr/local/opt/mysql@8.4/bin /opt/homebrew/bin /usr/local/bin /usr/bin; do
    [[ -n "$d" && -x "$d/mysql" ]] && { echo "$d"; return; }
  done
  command -v mysql >/dev/null && dirname "$(command -v mysql)"
}

find_php() {
  local p
  for p in "$PHP_BIN" "$HOME/php/php" /opt/homebrew/bin/php /usr/local/bin/php /usr/bin/php; do
    [[ -n "$p" && -x "$p" ]] && { echo "$p"; return; }
  done
  command -v php
}

MYSQL_DIR="$(find_mysql_dir)"
PHP="$(find_php)"
if [[ -z "$MYSQL_DIR" ]]; then
  echo "MySQL was not found. Install MySQL 8 (see docs/01_Setup_Guide.md) or set MYSQL_BIN_DIR."
  exit 1
fi
MYSQL="$MYSQL_DIR/mysql"
MYSQLADMIN="$MYSQL_DIR/mysqladmin"
