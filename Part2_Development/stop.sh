#!/bin/zsh
# ML IT Helpdesk - stop the MySQL server (asks for the root password)
cd "$(dirname "$0")"
source deploy/find_tools.sh
"$MYSQLADMIN" -uroot -p shutdown && echo "MySQL stopped."
