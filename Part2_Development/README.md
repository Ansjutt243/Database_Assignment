# ML IT Helpdesk System – Part 2: Development (MySQL + PHP)

The working pilot system built from the Part 1 Design Document for Manzaneque Limited (ML).

* **Database:** MySQL 8.0. It has 16 tables, 6 views, 7 triggers and 13 stored procedures, and enforces role-based security with GRANTs.
* **Application:** PHP 8.2 (no framework). It covers every screen in the wireframes.
* **Web server:** Apache 2.4 with HTTPS in front of the application.

## Quick start

```bash
cd Part2_Development
./start.sh                          # MySQL + application  → http://127.0.0.1:8080
./setup.sh                          # first time only (second terminal): build the database + 41 tests
./deploy/apache/start_apache.sh     # optional: HTTPS      → https://localhost:8443
./deploy/schedule/install_schedule.sh   # once: nightly backup at 18:00 (Mon–Sat)
```

Log in as **`a.khan`** (Operator), **`u.tariq`** (Technician), **`i.shah`** (Manager) or **`w.anwar`** (Admin). The password is **`Helpdesk2026`**.

## Folders

| Folder | Contents |
|---|---|
| [`database/`](database/) | SQL scripts 01–06, run in order |
| [`app/`](app/) | PHP application. Only `app/public/` is served; `app/cli/` holds scheduled jobs |
| [`deploy/`](deploy/) | Apache + HTTPS configuration and the nightly-backup schedule |
| [`tests/`](tests/) | Performance test (NFR-01) and browser test (NFR-09) with their results |
| [`docs/`](docs/) | Step-by-step documentation and screenshots |

## Documentation

| # | Document | What it covers |
|---|---|---|
| 1 | [Setup Guide](docs/01_Setup_Guide.md) | Installing, building the database, starting, HTTPS, nightly backup, pilot accounts |
| 2 | [Project Structure](docs/02_Project_Structure.md) | Every folder and file, the architecture, how a page works |
| 3 | [Database Implementation](docs/03_Database_Implementation.md) | Tables, constraints, views, triggers, procedures, pilot data, changes from the design |
| 4 | [Security, Roles & Permissions](docs/04_Security_Roles_Permissions.md) | Access matrix in the app and in MySQL, authentication, HTTPS |
| 5 | [System Flows](docs/05_System_Flows.md) | DFDs and a flowchart plus steps for every function |
| 6 | [Testing](docs/06_Testing.md) | Database, access, end-to-end, maintenance, performance and browser tests |

## Design Document → implementation status

| Design Document item | Status | Where / evidence |
|---|---|---|
| UR-01 – UR-18 user requirements | ✅ | screens in `app/public/`; [Testing](docs/06_Testing.md) |
| FR-01 – FR-16 functional requirements | ✅ | 41 database tests + 32 browser tests |
| NFR-01 performance with 50,000 records | ✅ slowest report 0.98 s (limit 5 s) | `tests/performance_results.md` |
| NFR-02 security (bcrypt, lock-out, 20-min timeout, least privilege) | ✅ | `Auth.php`, `04_security_roles.sql` |
| NFR-03 usability (≤ 3 clicks to log a call, one layout, clear errors) | ✅ | Main → Log New Call → Save & Assign |
| NFR-04 integrity / transactions / daily backup | ✅ | `sp_LogNewProblem`, nightly schedule |
| NFR-07 3NF, naming, data dictionary, SQL under version control | ✅ | `database/`, git repository |
| NFR-09 current browsers | ✅ Chrome (Edge engine) 32/32 · Firefox script ready | `tests/browser_results_chrome.md` |
| 7.1 Data dictionary, 7.4 indexes, 7.5 pilot data | ✅ | `01_create_tables.sql`, `03_pilot_data.sql` |
| 7.3 Views, triggers, procedures | ✅ (more than planned) | `02_views_triggers_procedures.sql` |
| 8 Validation (3 layers) and verification | ✅ | HTML5 + `Validator.php` + constraints/triggers |
| 9 Interface: all screens, navigation, problem number shown before saving | ✅ | `docs/screenshots/chrome/` |
| 10 Reports R1–R6 | ✅ | `reports/`, `sp_Report_R1`–`R6` |
| 11 Access matrix, MySQL roles | ✅ | `Auth::PERMISSIONS`, `04_security_roles.sql` |
| 12 Backup (all + nightly 18:00), restore + compact (operators), integrity check (admin, monthly) | ✅ | `Maintenance.php`, `app/cli/`, `deploy/schedule/` |
| 2.3.3 Apache 2.4, HTTPS | ✅ pilot: Apache + TLS 1.2 in front of the PHP server | `deploy/apache/` |
