# 06 – Testing

Testing was done on five levels:

1. **Database tests**: 41 automated tests inside MySQL (`database/06_validation_tests.sql`).
2. **Access-control tests**: every page opened by every role, plus the MySQL accounts directly.
3. **End-to-end tests**: complete flows through the web application, including maintenance.
4. **Performance test**: 50,000 problem records (NFR-01).
5. **Browser test**: a real browser over HTTPS, with screenshots (NFR-09).

All results below are from the pilot system on 02/11/2026.

---

## 1. Database tests (automated)

**How to run:** `./setup.sh`, or `mysql -uroot -p -t mlhelpdesk < database/06_validation_tests.sql`.

Each test runs inside a transaction that is **rolled back**, so the pilot data never changes. The result table has an *Expected* column and an *Actual* column, and the test passes only when they match.

**Result: 41 run · 41 passed · 0 failed**

| ID | Requirement / rule | Test | Test data | Expected | Actual | Result |
|---|---|---|---|---|---|---|
| TT-01 | NFR-04 | New problem + call saved as one transaction; failed call rolls back problem | rows before=60 after=60 | Rejected | Rejected | PASS |
| TT-02 | UR-05, FR-03 | Valid new call gets an automatic problem number | next number | Accepted | Accepted | PASS |
| VT-01 | BR-04 | Caller must be a current employee | Zara Noor (left 30/06/2025) | Rejected | Rejected | PASS |
| VT-02 | UR-01 | Valid problem by current employee | Bilal Ahmed, Printer, High | Accepted | Accepted | PASS |
| VT-03 | UR-06 | Reason for call is always required | (blank) | Rejected | Rejected | PASS |
| VT-04 | UR-06 | Follow-up call recorded against existing problem | Chasing - deadline today | Accepted | Accepted | PASS |
| VT-05 | Referential | Follow-up must quote an existing problem number | P-999999 | Rejected | Rejected | PASS |
| VT-06 | UR-14, BR-10 | Only helpdesk operators can log calls | i.shah (Manager) | Rejected | Rejected | PASS |
| VT-07 | Presence/length | Description at least 10 characters | Broken | Rejected | Rejected | PASS |
| VT-08 | List | Priority must be Low/Medium/High/Critical | Very high | Rejected | Rejected | PASS |
| VT-09 | Lookup | Problem type must be chosen from the list | ProblemTypeID 99 | Rejected | Rejected | PASS |
| VT-10 | FR-09, BR-11 | Technician must have the specialism | Hamza Ali for Printer | Rejected | Rejected | PASS |
| VT-11 | UR-08 | Skilled technician assigned; time stamped by trigger | Usman Tariq for Printer | Accepted | Accepted | PASS |
| VT-12 | Cross-field | Resolved time must be after logged time | resolved 2 days before logged | Rejected | Rejected | PASS |
| VT-13 | Range | Resolved time cannot be in the future | tomorrow | Rejected | Rejected | PASS |
| VT-14 | Range | Time taken between 0.25 and 500 hours | 0 | Rejected | Rejected | PASS |
| VT-15 | BR-12 | Closed problem needs resolution notes | (blank) | Rejected | Rejected | PASS |
| VT-16 | Access matrix | Technicians resolve own jobs only | u.tariq on another technician's job | Rejected | Rejected | PASS |
| VT-17 | UR-09, FR-10 | Valid resolution closes the problem | now, 0.75 h, notes | Accepted | Accepted | PASS |
| VT-18 | Format | Employee ID = E + 4 digits | E45A | Rejected | Rejected | PASS |
| VT-19 | Format | Email must contain @ and a dot after it | b.ahmed@ml | Rejected | Rejected | PASS |
| VT-20 | Uniqueness | Email must not already exist | bilal.ahmed@ml.co.uk | Rejected | Rejected | PASS |
| VT-21 | Format | Phone: digits, spaces, + and - only | 042-ABC | Rejected | Rejected | PASS |
| VT-22 | BR-03 | End date cannot be before start date | start 2020, end 2019 | Rejected | Rejected | PASS |
| VT-23 | Range | Start date cannot be in the future | next month | Rejected | Rejected | PASS |
| VT-24 | UR-10 | Valid employee (apostrophe in name, + in phone) | E0906 Test O'Brien | Accepted | Accepted | PASS |
| VT-25 | Cross-field | Warranty cannot end before purchase | purchase 2025, warranty 2024 | Rejected | Rejected | PASS |
| VT-26 | Uniqueness | Serial number must be unique | HP-5CG1234XYZ | Rejected | Rejected | PASS |
| VT-27 | Format | Serial: letters, digits and - only | HP 123/45 | Rejected | Rejected | PASS |
| VT-28 | Range | Seats must be 1 to 10,000 | 0 | Rejected | Rejected | PASS |
| VT-29 | BR-01 | Specialisation from the fixed list of seven | Retail | Rejected | Rejected | PASS |
| VT-30 | Length | Username 4-30 characters | ak | Rejected | Rejected | PASS |
| VT-31 | NFR-02 | Only bcrypt hashes stored, never plain text | Helpdesk2026 | Rejected | Rejected | PASS |
| VT-32 | BR-13 | Role must be Operator/Technician/Manager/Admin | Director | Rejected | Rejected | PASS |
| VT-33 | UR-16 | Only operators can restore | i.shah (Manager) | Rejected | Rejected | PASS |
| VT-34 | UR-15 | Backup is available to every user | i.shah (Manager) | Accepted | Accepted | PASS |
| VT-35 | FR-12 | Cannot delete an office that has employees | Lahore | Rejected | Rejected | PASS |
| VT-36 | FR-16 | Employees with history are end-dated, not deleted | E0045 | Rejected | Rejected | PASS |
| VT-37 | Table 41 | Only the administrator runs the integrity check | a.khan (Operator) | Rejected | Rejected | PASS |
| VT-38 | Table 41 | Scheduled backup logged as System | UserID NULL, Backup | Accepted | Accepted | PASS |
| VT-39 | UR-16 | Restore always records who did it | UserID NULL, Restore | Rejected | Rejected | PASS |

---

## 2. Access-control tests (UR-13, UR-14, UR-16, FR-02)

Each page was requested while logged in as each role. 200 = page opens, 403 = Access denied.

| Page | Operator | Technician | Manager | Admin | Matches Table 40? |
|---|:-:|:-:|:-:|:-:|:-:|
| Main Control Page | 200 | 200 | 200 | 200 | ✅ |
| Log New Call / Follow-up / Assign | 200 | 403 | **403** | 403 | ✅ managers cannot log jobs |
| Resolve Problem | 200 | 200 | 403 | 403 | ✅ |
| Open Problems / Problem details | 200 | 200 (own) | 200 | 200 | ✅ |
| Management Reports (menu + R1–R6) | **403** | 403 | 200 | 403 | ✅ operators cannot open reports |
| Registers (all five) | 200 view | 200 view | 200 view | 200 edit | ✅ |
| POST to a register as Operator | 403 | – | – | 302 saved | ✅ |
| Maintenance (backup) | 200 | 200 | 200 | 200 | ✅ everyone can back up |
| Restore as Manager | – | – | **403** | – | ✅ |
| User Accounts | 403 | 403 | 403 | 200 | ✅ |

**Database layer (application bypassed):**

| Test | Result |
|---|---|
| `ml_operator` runs `CALL sp_Report_R1(2026,10)` | ERROR 1370 execute command denied ✅ |
| `ml_manager` runs `INSERT INTO Problem …` | ERROR 1142 INSERT command denied ✅ |
| `ml_operator` runs `SELECT PasswordHash FROM AppUser` | ERROR 1142 SELECT command denied ✅ |

---

## 3. End-to-end tests (web application)

| # | Flow | Steps and data | Expected | Actual | Result |
|---|---|---|---|---|---|
| E1 | Log call – invalid | serial `ABC-000`, blank reason, description `Broken` | 3 field errors, nothing saved | “Serial number not found…”, “Please enter the reason…”, “Please describe the problem (min. 10…)” | PASS |
| E2 | Log call – ex-employee | caller E0012 Zara Noor | refused | “Caller not found or no longer employed.” | PASS |
| E3 | Log call – valid | Bilal Ahmed, HP-5CG1234XYZ, Office software, High, Distressed | problem number shown, go to Assign | “Problem P-000065 logged…” | PASS |
| E4 | Assign – load balancing | Office software | skilled technicians only, least busy recommended | Hamza Ali (1 open job) recommended; Kamran (printers only) not listed | PASS |
| E5 | Assign – unskilled (forced POST) | Kamran Javed → Office software | refused by trigger | “This technician is not skilled in this problem type.” | PASS |
| E6 | Follow-up | `P-000065`, Distressed, “Chasing progress…” | call added | call history shows 2 calls | PASS |
| E7 | Resolve – another technician's job | h.ali resolves Usman's job | refused | “Technicians can only resolve their own jobs.” | PASS |
| E8 | Resolve – invalid hours | 0 hours | refused | “Time taken must be between 0.25 and 500 hours.” | PASS |
| E9 | Resolve – valid | now, 0.5 h, notes | Status = Closed | “P-000065 has been resolved and closed.” | PASS |
| E10 | Reports R1–R6 | October 2026, Usman Tariq, Lahore | all six render with totals | all render; R5 totals 3 open / 2 closed / 2.00 h | PASS |
| E11 | Lock-out | 5 wrong passwords for `t.oneil` | locked; correct password refused | 5th attempt: “Too many failed attempts…”; then “This account is locked.” | PASS |
| E12 | Unlock | Admin → Unlock | can log in again | log-in succeeds | PASS |
| E13 | Duplicate email | new employee with `bilal.ahmed@ml.co.uk` | refused | “This email address is already in use.” | PASS |
| E14 | Delete office with staff | delete Gwadar office | refused | “This record is still used elsewhere, so it cannot be deleted.” | PASS |
| E15 | Password typed twice | `abcdefg1` / `abcdefg2` | refused | “The two passwords do not match.” | PASS |
| E16 | Technician with no skills | untick all | refused | “A technician must have at least one specialism.” | PASS |

## 4. Maintenance tests (UR-15, UR-16)

| # | Test | Expected | Actual | Result |
|---|---|---|---|---|
| M1 | Manager clicks Back Up Now | `.sql` file created and logged | `mlhelpdesk_20261102_1449.sql` (135 KB): 16 tables, 7 triggers, 15 routines | PASS |
| M2 | Restore with the wrong confirmation date | cancelled | “Restore cancelled: type the backup date…” | PASS |
| M3 | Restore (operator), done twice | rows added after the backup disappear; all objects back | marker row gone; 15 routines, 7 triggers, 6 views, 60 problems | PASS |
| M4 | Roles after restore | operator still cannot run reports | ERROR 1370 | PASS |
| M5 | Compact (operator) | OPTIMIZE on all tables, logged | “Database compacted…” | PASS |
| M6 | Scheduled backup (`app/cli/scheduled_backup.php`) | file created, logged as System, exit code 0 | `mlhelpdesk_20261102_1505.sql`; log row “Scheduled backup / System / Success” | PASS |
| M7 | Integrity check (admin) | CHECK + ANALYZE on 16 tables, logged | “Integrity check passed: all 16 tables are OK…” | PASS |
| M8 | Integrity check (operator) | refused | 403 | PASS |
| M9 | HTTPS through Apache | TLS, redirect, secure cookie, private folders hidden | TLS 1.2 ECDHE-RSA-AES256-GCM; HTTP 301 → HTTPS; cookie `Secure`; `/config/database.php` → 404 | PASS |

## 5. Performance test (NFR-01)

**How to run:** `~/php/php tests/performance_test.php`. The script adds 50,000 generated problems and calls, times each look-up and report three times with the real application accounts, then deletes the generated rows.

Run on 02/11/2026 15:08 with **50,061 problem records** and the same number of calls (MySQL 8.0, PHP 8.2, pilot laptop). Each test was run 3 times; *Slowest* is the figure checked against the limit.

| Req. | Test | MySQL account | Rows returned | Average (s) | Slowest (s) | Limit (s) | Result |
|---|---|---|---:|---:|---:|---:|---|
| UR-02 | Caller search by partial name | ml_operator | 2 | 0.001 | 0.002 | 2 | PASS |
| UR-03 | Equipment look-up by serial + warranty | ml_operator | 1 | 0.001 | 0.001 | 2 | PASS |
| UR-04 | Installed software + licence status | ml_operator | 6 | 0.001 | 0.001 | 2 | PASS |
| UR-08 | Skilled technicians with open-job count | ml_operator | 3 | 0.003 | 0.004 | 2 | PASS |
| UR-06 | Find problem by number (follow-up) | ml_operator | 1 | 0.001 | 0.002 | 2 | PASS |
| UR-12 | Report R1 | ml_manager | 3,457 | 0.095 | 0.103 | 5 | PASS |
| UR-12 | Report R2 | ml_manager | 7 | 0.085 | 0.086 | 5 | PASS |
| UR-12 | Report R3 | ml_manager | 15 | 0.086 | 0.087 | 5 | PASS |
| UR-12 | Report R3 (all time) | ml_manager | 15 | 0.165 | 0.166 | 5 | PASS |
| UR-12 | Report R4 | ml_manager | 48 | 0.435 | 0.444 | 5 | PASS |
| UR-12 | Report R5 | ml_manager | 721 | 0.050 | 0.053 | 5 | PASS |
| UR-12 | Report R6 | ml_manager | 345 | 0.955 | 0.984 | 5 | PASS |

**12 of 12 tests within the NFR-01 limits.** The generated rows were deleted after the test.

The slowest report (R6, with three result tables) took about 1 second, a fifth of the 5-second limit. Look-ups took a few milliseconds against a 2-second limit. This comes from the indexes in Design Document 7.4 (`idx_problem_logged`, `idx_problem_status`, the foreign-key indexes and the UNIQUE serial number index).

## 6. Browser test (NFR-09)

**How to run:** `python3 tests/browser_test.py chrome https://localhost:8443`. This drives a real browser through every main flow, over HTTPS through Apache.

**Chrome 150 (Chromium engine, the same engine as Microsoft Edge): 32 of 32 passed.** Full list: `tests/browser_results_chrome.md`. Screenshots: `docs/screenshots/chrome/` (27 screens). The checks include:

* JavaScript auto-fill: the caller's job title and office; the equipment type, warranty badge and installed software with licence status; the OS pre-selected.
* The problem number shown before saving equals the number given on saving.
* The confirmation dialogs appear (verification, Design 8.4).
* The browser blocks empty required fields (validation layer 1).
* The least busy skilled technician is recommended, and the operator can override the recommendation.
* Each role sees only its menus. Forbidden pages show *Access denied*.
* All six reports render; backup works from the Main Control Page; the integrity check runs.
* There are no JavaScript errors on any page.

**Firefox 147:** the same script supports Firefox (WebDriver BiDi). It could not run inside the development sandbox, which stops Firefox at start-up, so it has to be run on a normal desktop with `python3 tests/browser_test.py firefox`. The pages use only standard HTML5, CSS and plain JavaScript (`fetch`, `querySelector`, `requestSubmit`), all supported by current Chrome, Edge and Firefox.

**Safari 17.6:** not automated. Safari's WebDriver must first be enabled by an administrator with `safaridriver --enable`.

### Defects found and fixed during testing

| Defect | Cause | Fix | Retest |
|---|---|---|---|
| VT-17 failed at first | `NOW()` is fixed for the whole procedure call, so resolved = logged | test problem logged 3 hours earlier | PASS |
| Call history page failed for operators | page joined `AppUser`, which operators cannot read | new view `vw_CallDetail` (names only, no hashes) | PASS |
| Restore stopped half-way (error 1419) | binary logging needs SUPER to create triggers | `log_bin_trust_function_creators = ON` | PASS |
| Restore refused (error 1227) | objects owned by root (a SYSTEM_USER) | new locked owner account `ml_owner` | PASS (M3) |
| Database left half-restored after a failed restore | no recovery step | Restore now reloads the safety backup automatically | PASS |
| Generic message when deleting an office with staff | MySQL returned 1217 instead of 1451 | mapped 1217 to the friendly message | PASS (E14) |
| A job resolved in the same minute it was logged was refused (browser test) | the resolved time field had no seconds (15:15:00 < 15:15:27) | the field now includes seconds | PASS (browser test) |
| Report pages showed an extra empty “No data” table | the procedure's final status result was shown as a table | only the report's own tables are shown | PASS |
| The performance test stopped and left its data behind | `ANALYZE TABLE` returns rows that were not read | rows read; leftovers removed automatically at the next start | PASS |
| CLI scripts failed silently | the error handler was registered only for web pages | the handler now prints errors in the terminal too | PASS |

---

## 7. Choice of test data

* **Normal data:** real-looking pilot data (60 problems over three months) so that every report has rows, totals and averages that can be checked by hand.
* **Boundary data:** 0.25 and 500 hours (the limits); 0 hours (just outside); 3-character reason; 10-character description; 4-character username; seats 1 and 0.
* **Invalid / erroneous data:** wrong formats (`E45A`, `b.ahmed@ml`, `042-ABC`, `HP 123/45`); values not in a list (`Very high`, `Retail`, `Director`); duplicates; unknown keys (`P-999999`, type 99).
* **Business-rule data:**
  * an ex-employee (Zara Noor) as caller;
  * a technician without the skill (Hamza for printers, Kamran for Office software);
  * a technician on leave (Kamran Javed);
  * a locked account (`r.aslam`);
  * expired warranties and licences;
  * one item that fails repeatedly under warranty and one out of warranty, so R4 shows both actions.
* **Security data:** every role tried every page; the database accounts were also tested directly to prove the rules hold without the application.
