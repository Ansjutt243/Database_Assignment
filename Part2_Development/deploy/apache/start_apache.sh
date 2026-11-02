#!/bin/zsh
# =====================================================================
# Starts Apache 2.4 with HTTPS in front of the ML IT Helpdesk.
#   ./deploy/apache/start_apache.sh         start  -> https://localhost:8443
#   ./deploy/apache/start_apache.sh stop    stop
# Run ./start.sh first (MySQL + PHP application server on port 8080).
# A self-signed certificate is created on first use (browser warning is
# expected for a pilot; production uses ML's own certificate).
# =====================================================================
set -e
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DIR="$ROOT/deploy/apache"
CONF="$DIR/run/httpd.conf"
mkdir -p "$DIR/run" "$DIR/ssl"

if [[ "$1" == "stop" ]]; then
  [[ -f "$DIR/run/httpd.pid" ]] && kill "$(cat "$DIR/run/httpd.pid")" && echo "Apache stopped."
  exit 0
fi

if [[ ! -f "$DIR/ssl/server.key" ]]; then
  echo "Creating self-signed certificate ..."
  openssl req -x509 -newkey rsa:2048 -sha256 -days 365 -nodes \
    -keyout "$DIR/ssl/server.key" -out "$DIR/ssl/server.crt" \
    -subj "/O=Manzaneque Limited/CN=localhost" \
    -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" 2>/dev/null
  chmod 600 "$DIR/ssl/server.key"
fi

sed "s|@ROOT@|$ROOT|g" "$DIR/httpd-mlhelpdesk.conf" > "$CONF"
/usr/sbin/httpd -t -f "$CONF"
/usr/sbin/httpd -f "$CONF" -k start
echo "Apache running:  https://localhost:8443   (http://localhost:8081 redirects to HTTPS)"
