#!/bin/zsh
# =====================================================================
# Runs the Playwright A-to-Z flow test with video (docs/08_E2E_Flow_Test.md)
#   ./tests/e2e/run_e2e.sh                 (system must be running: ./start.sh)
#   BASE_URL=https://localhost:8443 ./tests/e2e/run_e2e.sh
#
# 1. saves the current database (the test adds and deletes records)
# 2. runs the 7 flows in Google Chrome and records one video per flow
# 3. converts the videos to MP4 in docs/videos/
# 4. puts the database back exactly as it was and re-grants the roles
# =====================================================================
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$DIR/../.." && pwd)"
MYSQL="$HOME/mysql/bin/mysql"
DUMP="$HOME/mysql/bin/mysqldump"
SAVE="$DIR/results/before_e2e.sql"

echo -n "MySQL root password: "
read -s ROOTPW; echo
export MYSQL_PWD="$ROOTPW"
mkdir -p "$DIR/results"

echo "Saving the current database ..."
"$DUMP" -uroot --socket=/tmp/mysql.sock --single-transaction --routines --triggers --events \
        --databases mlhelpdesk > "$SAVE"

# Playwright needs ffmpeg to record video; on macOS 12 it cannot download its own,
# so it uses the system ffmpeg (brew install ffmpeg)
FF="$HOME/Library/Caches/ms-playwright/ffmpeg-1013/ffmpeg-mac"
if [[ ! -e "$FF" ]] && command -v ffmpeg >/dev/null; then
  mkdir -p "$(dirname "$FF")" && ln -sf "$(command -v ffmpeg)" "$FF"
fi

cd "$DIR"
[[ -d node_modules ]] || npm install --silent
rm -rf results/raw "$ROOT/docs/screenshots/e2e"
set +e
npx playwright test
RESULT=$?
set -e

echo "Converting videos to MP4 ..."
mkdir -p "$ROOT/docs/videos"
names=(01_login_lockout 02_admin_registers_users_crud 03_operator_call_flow 04_technician_resolve
       05_manager_reports 06_maintenance 07_access_control)
for i in {1..7}; do
  src=(results/raw/*-0${i}-*/video.webm(N))
  [[ -n "$src" ]] && ffmpeg -y -loglevel error -i "$src[1]" -c:v libx264 -pix_fmt yuv420p -crf 26 \
                            -movflags +faststart "$ROOT/docs/videos/${names[$i]}.mp4"
done

echo "Putting the database back as it was ..."
"$MYSQL" -uroot --socket=/tmp/mysql.sock < "$SAVE"
# a dump drops and re-creates the procedures, which removes their EXECUTE grants
"$MYSQL" -uroot --socket=/tmp/mysql.sock mlhelpdesk < "$ROOT/database/04_security_roles.sql" > /dev/null

echo "Videos:      docs/videos/"
echo "Screenshots: docs/screenshots/e2e/"
exit $RESULT
