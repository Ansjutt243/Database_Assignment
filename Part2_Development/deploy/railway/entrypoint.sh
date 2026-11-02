#!/bin/bash
# =====================================================================
# Container start-up for the ML IT Helpdesk application:
#   1. prepares storage/ (a Railway volume is mounted there)
#   2. installs the nightly backup (18:00 Mon-Sat, Design Doc Table 41)
#   3. starts cron and Apache
# =====================================================================
set -e
export PORT="${PORT:-8080}"
APP=/var/www/app

# 1. storage folders (an empty volume hides the ones from the image)
mkdir -p "$APP/storage/backups" "$APP/storage/logs"
chown -R www-data:www-data "$APP/storage"

# 2. cron does not pass the container's variables to jobs, so the
#    database settings are written to a file only www-data can read
ENVFILE=/etc/mlhelpdesk.env
: > "$ENVFILE"
while IFS='=' read -r key value; do
  case "$key" in
    DB_*|ML_*|MYSQL_BIN|MYSQLDUMP_BIN|TRUST_PROXY|TZ) printf 'export %s=%q\n' "$key" "$value" >> "$ENVFILE" ;;
  esac
done < <(env)
chown www-data:www-data "$ENVFILE"
chmod 600 "$ENVFILE"

cat > /etc/cron.d/mlhelpdesk-backup <<EOF
SHELL=/bin/bash
0 18 * * 1-6 www-data . $ENVFILE && /usr/local/bin/php $APP/cli/scheduled_backup.php >> $APP/storage/logs/schedule.log 2>&1
EOF
chmod 644 /etc/cron.d/mlhelpdesk-backup

# 3. start
cron
exec apache2-foreground
