#!/bin/zsh
# =====================================================================
# Runs the browser test (NFR-09) in its own Python virtual environment,
# so nothing is installed into the system Python (PEP 668).
#   ./tests/run_browser_test.sh chrome
#   ./tests/run_browser_test.sh firefox
#   ./tests/run_browser_test.sh firefox http://127.0.0.1:8080
# The first run creates tests/.venv and installs websocket-client.
# =====================================================================
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
VENV="$DIR/.venv"

if [[ ! -x "$VENV/bin/python" ]]; then
  echo "Creating Python environment in tests/.venv (first run only) ..."
  python3 -m venv "$VENV"
  "$VENV/bin/python" -m pip install --quiet websocket-client
fi

exec "$VENV/bin/python" "$DIR/browser_test.py" "${1:-chrome}" "${2:-https://localhost:8443}"
