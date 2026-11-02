#!/bin/zsh
# =====================================================================
# Builds the mlhelpdesk database on the cloud MySQL server (Railway) from
# this computer, then makes it safe to put online:
#   1. runs database scripts 01-04 (same as ./setup.sh)
#   2. gives the 7 application MySQL accounts new RANDOM passwords
#      (the pilot passwords in 04_security_roles.sql are public)
#   3. optionally sets a new log-in password for all application users
#   4. runs the 41 database tests
#   5. writes the variables the app container needs to deploy/railway/app.env
#
# Usage:  ./deploy/railway/init_remote_db.sh <host> <port>
#   host/port = the MySQL service's public TCP proxy shown by Railway,
#   e.g. ./deploy/railway/init_remote_db.sh shuttle.proxy.rlwy.net 41234
# WARNING: deletes any existing mlhelpdesk database on that server.
# =====================================================================
set -e
HOST="$1"; PORT="$2"
if [[ -z "$HOST" || -z "$PORT" ]]; then
  echo "Usage: $0 <mysql-host> <mysql-port>"; exit 1
fi
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
MYSQL="${MYSQL_BIN:-$HOME/mysql/bin/mysql}"
PHP="${PHP_BIN:-$HOME/php/php}"
OUT="$ROOT/deploy/railway/app.env"

echo -n "MySQL root password of the Railway MySQL service (MYSQL_ROOT_PASSWORD): "
read -s ROOTPW; echo
export MYSQL_PWD="$ROOTPW"
M=("$MYSQL" --protocol=TCP -h "$HOST" -P "$PORT" -uroot)
"${M[@]}" -e "SELECT VERSION() AS 'Connected to MySQL';"

echo "1. Building the database ..."
"${M[@]}" < "$ROOT/database/01_create_tables.sql" > /dev/null
for f in 02_views_triggers_procedures.sql 03_pilot_data.sql 04_security_roles.sql; do
  echo "   $f"
  "${M[@]}" mlhelpdesk < "$ROOT/database/$f" > /dev/null
done

echo "2. New random passwords for the application's MySQL accounts ..."
: > "$OUT"; chmod 600 "$OUT"
for pair in auth:AUTH operator:OPERATOR technician:TECHNICIAN manager:MANAGER admin:ADMIN backup:BACKUP maint:MAINT; do
  user="ml_${pair%%:*}"; var="ML_${pair##*:}_PASSWORD"
  pw="$(openssl rand -hex 20)"
  "${M[@]}" -e "ALTER USER '$user'@'%' IDENTIFIED BY '$pw';"
  echo "$var=$pw" >> "$OUT"
done

echo "3. Log-in password for the application users (a.khan, i.shah, w.anwar ...)"
echo "   Leave empty to keep the pilot password Helpdesk2026 (NOT recommended online)."
echo -n "   New password (8+ characters, a letter and a number): "; read -s APPPW; echo
if [[ -n "$APPPW" ]]; then
  echo -n "   Type it again: "; read -s APPPW2; echo
  [[ "$APPPW" == "$APPPW2" ]] || { echo "Passwords do not match - nothing changed."; exit 1; }
  HASH="$("$PHP" -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' -- "$APPPW")"
  "${M[@]}" mlhelpdesk -e "UPDATE AppUser SET PasswordHash = '$HASH', FailedLogins = 0;"
  echo "   Done: every application user now logs in with the new password."
fi

echo "4. Running the database tests ..."
"${M[@]}" -t mlhelpdesk < "$ROOT/database/06_validation_tests.sql" | tail -5
unset MYSQL_PWD

cat >> "$OUT" <<EOF
DB_NAME=mlhelpdesk
DB_PORT=3306
TRUST_PROXY=1
EOF

echo
echo "5. Variables for the app service were saved to deploy/railway/app.env (keep it private)."
echo "   In Railway set them on the APP service, plus DB_HOST = the MySQL service's private host"
echo "   (for example: mysql.railway.internal). Then redeploy the app."
