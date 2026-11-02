# =====================================================================
# ML IT Helpdesk - application container (PHP 8.2 + Apache 2.4)
# Used for cloud hosting (Railway). The MySQL 8 server runs in its own
# container: deploy/railway/mysql/. See docs/07_Deployment_Railway.md.
# =====================================================================
FROM php:8.2-apache-bookworm

# MariaDB client tools (mariadb-dump / mariadb) for Backup and Restore,
# cron for the nightly 18:00 backup, PDO MySQL driver for the app.
RUN apt-get update \
 && apt-get install -y --no-install-recommends mariadb-client cron \
 && docker-php-ext-install pdo_mysql \
 && a2enmod headers \
 && rm -rf /var/lib/apt/lists/* \
 && ln -sf /usr/share/zoneinfo/Asia/Karachi /etc/localtime \
 && echo "Asia/Karachi" > /etc/timezone

ENV TZ=Asia/Karachi \
    MYSQLDUMP_BIN=/usr/bin/mariadb-dump \
    MYSQL_BIN=/usr/bin/mariadb \
    TRUST_PROXY=1 \
    PORT=8080

# Only app/ goes into the image; app/public is the web root
COPY app/ /var/www/app/
COPY deploy/railway/apache-site.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/railway/ports.conf       /etc/apache2/ports.conf
COPY deploy/railway/php.ini          /usr/local/etc/php/conf.d/zz-mlhelpdesk.ini
COPY deploy/railway/entrypoint.sh    /usr/local/bin/ml-entrypoint

RUN chmod +x /usr/local/bin/ml-entrypoint \
 && rm -rf /var/www/html \
 && chown -R www-data:www-data /var/www/app/storage

EXPOSE 8080
CMD ["ml-entrypoint"]
