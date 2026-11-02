# 01 – Setup Guide (step by step)

How to install and run the ML IT Helpdesk pilot on a computer. Follow the steps in order.

This guide describes the development computer (macOS, portable MySQL and PHP in the home folder). **To install on another computer, Windows or macOS, with the normal MySQL and PHP installers, follow the [Installation Guide](../../Part3_Documentation/ML_Helpdesk_Installation_Guide.docx).** The scripts find MySQL and PHP in either place: `setup.sh` / `start.sh` on macOS and Linux (through `deploy/find_tools.sh`), and `setup.bat` / `start.bat` on Windows.

| Component | Version used in the pilot | Where it is installed |
|---|---|---|
| Database server | MySQL Community Server 8.0.31 (InnoDB, utf8mb4) | `~/mysql` |
| Application language | PHP 8.2.32 with `pdo_mysql` | `~/php/php` |
| Web server | PHP built-in server (pilot); Apache 2.4 for production | – |
| Browser | Chrome, Edge or Firefox (NFR-09) | – |

---

## Step 1 – Install MySQL 8.0

1. Download the MySQL 8.0 build for your OS from <https://dev.mysql.com/downloads/mysql/>. The pilot used the macOS 12 x86_64 TAR archive.
2. Unpack it into `~/mysql`.
3. Create the configuration file `~/mysql/my.cnf`:

   ```ini
   [mysqld]
   basedir=/Users/<you>/mysql
   datadir=/Users/<you>/mysql/data
   port=3306
   bind-address=127.0.0.1          # only this computer can connect
   socket=/tmp/mysql.sock
   character-set-server=utf8mb4
   collation-server=utf8mb4_0900_ai_ci

   [client]
   socket=/tmp/mysql.sock
   ```

4. Create the data folder and set the root password:

   ```bash
   ~/mysql/bin/mysqld --defaults-file=$HOME/mysql/my.cnf --initialize-insecure
   ~/mysql/bin/mysqld_safe --defaults-file=$HOME/mysql/my.cnf &
   ~/mysql/bin/mysql -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '<root password>';"
   ```

> **Windows:** use the MySQL Installer from the same page. It installs the server, MySQL Workbench and the Windows service in one go.

## Step 2 – Install PHP 8.2

The application needs PHP 8.2 or later with the `pdo_mysql` and `session` extensions.

* **macOS (pilot):** a ready-built PHP from <https://dl.static-php.dev/static-php-cli/common/> (`php-8.2.x-cli-macos-x86_64.tar.gz`), unpacked to `~/php`.
* **Windows / Linux:** XAMPP, or PHP from <https://www.php.net/downloads>.

Check the install:

```bash
~/php/php -v
~/php/php -m | grep -i pdo_mysql      # must print pdo_mysql
```

## Step 3 – Build the database

From the `Part2_Development` folder:

```bash
./setup.sh
```

The script asks for the MySQL root password, then runs these scripts in order:

| # | Script | What it does |
|---|---|---|
| 1 | `database/01_create_tables.sql` | Creates the `mlhelpdesk` database, the locked `ml_owner` account and the 16 tables |
| 2 | `database/02_views_triggers_procedures.sql` | 6 views, 7 triggers, 13 stored procedures |
| 3 | `database/03_pilot_data.sql` | Pilot data (Design Document Table 30) |
| 4 | `database/04_security_roles.sql` | MySQL roles and the application's MySQL accounts |
| 6 | `database/06_validation_tests.sql` | Runs 41 automatic tests. The script must end with **41 Passed, 0 Failed** |

`database/05_queries_and_reports.sql` is read-only. Run it whenever you want to see the multi-table queries and the reports.

> **MySQL Workbench alternative:** open each script in the order above and run it with the ⚡ button.

## Step 4 – Start the system

```bash
./start.sh
```

This starts MySQL (if it is not already running) and the web server. Then open **<http://127.0.0.1:8080>**.

* Stop the web server with **Ctrl+C**.
* Stop MySQL with `./stop.sh`.
* After a restart of the computer, run `./start.sh` again.

## Step 5 – Log in with a pilot account

Every pilot account uses the password **`Helpdesk2026`**.

| Username | Role | What they can do |
|---|---|---|
| `a.khan`, `o.farooq`, `n.hussain`, `f.mehmood` | Helpdesk Operator | Log, follow up, assign and resolve calls; back up, restore and compact |
| `r.aslam` | Helpdesk Operator | **Locked** after 5 failed log-ins (to show the lock-out rule) |
| `h.ali`, `s.malik`, `u.tariq`, `k.javed`, `m.qureshi`, `a.siddiqui` | IT Technician | See and resolve their own jobs |
| `i.shah`, `f.naz`, `t.mahmood` | Office Manager | Six management reports, view only |
| `w.anwar` | System Administrator | Registers and user accounts |

## Step 6 – HTTPS through Apache 2.4 (Design Document 2.3.3)

Apache 2.4 sits in front of the application and provides HTTPS:

```bash
./deploy/apache/start_apache.sh          # → https://localhost:8443
./deploy/apache/start_apache.sh stop
```

* The first run creates a self-signed certificate in `deploy/apache/ssl/`. A browser warning is normal for the pilot.
* `http://localhost:8081` redirects to HTTPS. TLS 1.2 is used, HSTS is set, and the session cookie becomes `Secure`.
* Only `app/public` can be reached; `config/`, `src/` and `storage/` return 404.
* Configuration: `deploy/apache/httpd-mlhelpdesk.conf`.

## Step 7 – Nightly backup at 18:00 (Design Document Table 41)

```bash
./deploy/schedule/install_schedule.sh            # install (macOS launchd)
launchctl list | grep com.ml.helpdesk            # check
./deploy/schedule/install_schedule.sh --remove   # remove
```

This installs two jobs:
* a backup every Monday–Saturday at 18:00 (`app/cli/scheduled_backup.php`);
* a job that starts MySQL at log-in, so the backup always finds it running.

Each scheduled backup appears in the Maintenance log as **Scheduled backup / System**.

* **Linux:** add the line `0 18 * * 1-6  php /path/to/app/cli/scheduled_backup.php` with `crontab -e`.
* **Windows:** use Task Scheduler, daily at 18:00, to run the same command.

## Step 8 – Extra tests (optional)

| Test | Command | Checks |
|---|---|---|
| Performance (NFR-01) | `~/php/php tests/performance_test.php` | 50,000 problems: look-ups < 2 s, reports < 5 s. Removes its data afterwards |
| Browser (NFR-09) | `./tests/run_browser_test.sh chrome` (or `firefox`). The first run creates `tests/.venv` | Real browser: auto-fill, dialogs, roles, JavaScript errors. Saves screenshots to `docs/screenshots/` |

## Step 9 – Before go-live (production checklist)

1. Change every pilot password: the application accounts (Admin → User Accounts) and the MySQL accounts (`04_security_roles.sql` and `app/config/database.php`).
2. Run behind Apache 2.4 with HTTPS (Step 6) on ports 443/80. For many users, replace the PHP built-in server with PHP-FPM (`mod_proxy_fcgi`).
3. Install the nightly backup (Step 7) and replace the self-signed certificate with ML's own.
4. Copy `app/storage/backups` to a separate disk or network share.

## Troubleshooting

| Problem | Fix |
|---|---|
| `MySQL is not running` | Run `./start.sh` |
| Log-in page shows “Something went wrong” | Read `app/storage/logs/app.log`. Check that MySQL is running and that `setup.sh` was run |
| `Access denied for user 'ml_operator'` | Run `04_security_roles.sql` again (it recreates the accounts) |
| Account locked | Log in as `w.anwar` → User Accounts → **Unlock** |
| Backup fails | Check `mysqldump_bin` in `app/config/config.php` points to MySQL's `bin/mysqldump` |
