# 04 – Security, Roles and Permissions

The brief requires:

* helpdesk operators can log jobs but **cannot open any reports**;
* office managers **cannot log jobs** but can view all reports;
* backup is available to everyone, restore and compact to **helpdesk operators only**.

These rules are enforced **twice**: once by the PHP application and once by MySQL itself. If someone bypasses the application, the database still refuses.

## 1. Application roles and permissions (access control matrix)

Defined once in `app/src/Auth.php` (`Auth::PERMISSIONS`). Every page calls `Auth::requirePermission(...)`.

| Function | Permission | Operator | Technician | Manager | Admin |
|---|---|:-:|:-:|:-:|:-:|
| Log new call | `call.log` | ✅ | ❌ | ❌ | ❌ |
| Follow-up call | `call.followup` | ✅ | ❌ | ❌ | ❌ |
| Assign technician | `call.assign` | ✅ | ❌ | ❌ | ❌ |
| Resolve problem | `call.resolve` | ✅ | ✅ own jobs | ❌ | ❌ |
| Open problems list | `call.list` | ✅ all | ✅ own jobs | ✅ all | ✅ all |
| Problem details / search | `call.view` | ✅ | ✅ | ✅ | ✅ |
| Management reports R1–R6 | `reports.view` | ❌ | ❌ | ✅ | ❌ |
| View registers | `registers.view` | ✅ | ✅ | ✅ | ✅ |
| Edit registers | `registers.edit` | ❌ | ❌ | ❌ | ✅ |
| User accounts / unlock | `users.manage` | ❌ | ❌ | ❌ | ✅ |
| Back up database | `db.backup` | ✅ | ✅ | ✅ | ✅ |
| Restore database | `db.restore` | ✅ | ❌ | ❌ | ❌ |
| Compact database | `db.compact` | ✅ | ❌ | ❌ | ❌ |
| Integrity check (monthly) | `db.check` | ❌ | ❌ | ❌ | ✅ |

What the user sees:
* The menu and the Main Control Page show only allowed options. For example, an operator sees **Management Reports (locked)**.
* Opening a forbidden page by typing its URL gives **403 Access denied**.

## 2. Database accounts and roles (MySQL)

Created by `database/04_security_roles.sql`. The PHP application picks the MySQL account that matches the logged-in user's role (`Database::forRole()`).

| MySQL account | MySQL role | Can | Cannot |
|---|---|---|---|
| `ml_operator` | `r_operator` | SELECT registers; INSERT/UPDATE Problem; INSERT HelpdeskCall; EXECUTE job procedures, `sp_CompactDatabase` | EXECUTE any `sp_Report_*`; read AppUser |
| `ml_technician` | `r_technician` | SELECT jobs and registers; EXECUTE `sp_ResolveProblem` | INSERT/UPDATE any table directly |
| `ml_manager` | `r_manager` | SELECT; EXECUTE `sp_Report_R1`–`R6` | INSERT Problem / HelpdeskCall (cannot log jobs) |
| `ml_admin` | `r_admin` | INSERT/UPDATE/DELETE registers; manage AppUser | Change Problem / HelpdeskCall |
| `ml_auth` | – | Log-in page only: read AppUser, update FailedLogins / IsActive | Anything else |
| `ml_backup` | – | Read-only dump rights (SELECT, SHOW VIEW, TRIGGER, LOCK TABLES); EXECUTE `sp_LogMaintenance` for the scheduled backup | Change data |
| `ml_maint` | – | All rights on `mlhelpdesk` only, used for Restore (operators) and the integrity check (admin) | Other databases, user management |
| `ml_owner` | – | Owner of views, triggers and procedures. **Locked** (no log-in) | – |

Proof (from testing):

```text
ml_operator> CALL sp_Report_R1(2026,10);
ERROR 1370: execute command denied to user 'ml_operator'@'localhost' for routine 'mlhelpdesk.sp_Report_R1'

ml_manager> INSERT INTO Problem (...) VALUES (...);
ERROR 1142: INSERT command denied to user 'ml_manager'@'localhost' for table 'problem'

ml_operator> SELECT PasswordHash FROM AppUser;
ERROR 1142: SELECT command denied to user 'ml_operator'@'localhost' for table 'appuser'
```

## 3. Authentication (log-in)

| Measure | How | Requirement |
|---|---|---|
| Hashed passwords | `password_hash()` bcrypt. A CHECK constraint refuses anything that is not a 60-char `$2…$` hash | NFR-02 |
| Lock-out | 5 failed attempts → `IsActive = FALSE`; only the Admin can unlock | NFR-02 |
| Same error for wrong user or password | “Incorrect username or password.” | Security heuristic |
| Ex-employees cannot log in | EndDate in the past → refused | BR-03 |
| Session timeout | 20 minutes idle → logged out | NFR-02 |
| Session fixation | `session_regenerate_id()` at log-in and log-out | OWASP |
| Password policy | ≥ 8 characters with a letter and a number, typed twice | Design 8.3 / 8.4 |

## 4. Other protections

| Threat | Protection |
|---|---|
| SQL injection | PDO prepared statements with bound `?` parameters; `ATTR_EMULATE_PREPARES = false` |
| Cross-site scripting (XSS) | Every output passes through `e()` (`htmlspecialchars`) |
| Cross-site request forgery | Secret `_csrf` token in every POST form (`Csrf::check()`) |
| Click-jacking / sniffing | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff` |
| Error leaks | Errors go to `storage/logs/app.log`; users see a friendly message (`DbError`) |
| Path traversal in restore | Only file names matching `mlhelpdesk_YYYYMMDD_HHMM.sql` from the backup folder |
| Passwords on the command line | mysqldump/mysql get credentials from a temporary 0600 option file |
| Network exposure | MySQL listens on 127.0.0.1 only |
| Eavesdropping | HTTPS through Apache 2.4 (TLS 1.2, HSTS); HTTP redirects to HTTPS; session cookie `Secure` over HTTPS |
| Audit trail | OperatorUserID on every call, ResolvedByUserID on every closure, MaintenanceLog |
