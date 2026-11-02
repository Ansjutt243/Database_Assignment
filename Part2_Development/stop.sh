#!/bin/zsh
# ML IT Helpdesk - stop the MySQL server (asks for the root password)
"$HOME/mysql/bin/mysqladmin" --socket=/tmp/mysql.sock -uroot -p shutdown && echo "MySQL stopped."
