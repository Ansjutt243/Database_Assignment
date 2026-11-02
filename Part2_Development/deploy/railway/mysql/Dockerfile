# =====================================================================
# ML IT Helpdesk - MySQL 8.0 server container (Railway)
# Same MySQL version as the pilot, so tables, triggers, procedures and
# roles work exactly as built and tested in Part 2.
# Needs: variable MYSQL_ROOT_PASSWORD and a volume mounted at /var/lib/mysql
# =====================================================================
FROM mysql:8.0
COPY mlhelpdesk.cnf /etc/mysql/conf.d/mlhelpdesk.cnf
