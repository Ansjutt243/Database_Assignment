#!/bin/zsh
# =====================================================================
# Installs the scheduled jobs on macOS (launchd):
#   com.ml.helpdesk.mysql   - starts MySQL when the user logs in
#   com.ml.helpdesk.backup  - nightly backup at 18:00, Monday-Saturday
#                             (Design Document Table 41, NFR-05)
# Remove with:  ./install_schedule.sh --remove
# Linux: add to crontab ->  0 18 * * 1-6  php <path>/app/cli/scheduled_backup.php
# =====================================================================
set -e
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
AGENTS="$HOME/Library/LaunchAgents"
PHP="${PHP_BIN:-$HOME/php/php}"
LOG="$ROOT/app/storage/logs/schedule.log"

if [[ "$1" == "--remove" ]]; then
  for job in com.ml.helpdesk.backup com.ml.helpdesk.mysql; do
    launchctl unload "$AGENTS/$job.plist" 2>/dev/null || true
    rm -f "$AGENTS/$job.plist"
  done
  echo "Scheduled jobs removed."
  exit 0
fi

mkdir -p "$AGENTS"

# --- nightly backup, 18:00 Monday (1) to Saturday (6)
{
  echo '<?xml version="1.0" encoding="UTF-8"?>'
  echo '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">'
  echo '<plist version="1.0"><dict>'
  echo '  <key>Label</key><string>com.ml.helpdesk.backup</string>'
  echo '  <key>ProgramArguments</key><array>'
  echo "    <string>$PHP</string><string>$ROOT/app/cli/scheduled_backup.php</string>"
  echo '  </array>'
  echo '  <key>StartCalendarInterval</key><array>'
  for d in 1 2 3 4 5 6; do
    echo "    <dict><key>Weekday</key><integer>$d</integer><key>Hour</key><integer>18</integer><key>Minute</key><integer>0</integer></dict>"
  done
  echo '  </array>'
  echo "  <key>StandardOutPath</key><string>$LOG</string>"
  echo "  <key>StandardErrorPath</key><string>$LOG</string>"
  echo '</dict></plist>'
} > "$AGENTS/com.ml.helpdesk.backup.plist"

# --- start MySQL at log-in, so the 18:00 backup always finds it running
cat > "$AGENTS/com.ml.helpdesk.mysql.plist" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>Label</key><string>com.ml.helpdesk.mysql</string>
  <key>ProgramArguments</key><array>
    <string>$HOME/mysql/bin/mysqld_safe</string><string>--defaults-file=$HOME/mysql/my.cnf</string>
  </array>
  <key>RunAtLoad</key><true/>
</dict></plist>
EOF

for job in com.ml.helpdesk.mysql com.ml.helpdesk.backup; do
  launchctl unload "$AGENTS/$job.plist" 2>/dev/null || true
  launchctl load "$AGENTS/$job.plist"
done
echo "Installed. Nightly backup: Mon-Sat 18:00 -> $ROOT/app/storage/backups"
echo "Check with:  launchctl list | grep com.ml.helpdesk"
