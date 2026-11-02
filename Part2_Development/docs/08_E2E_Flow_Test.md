# 08 – End-to-End Flow Test with Video (Playwright)

The whole system is tested from A to Z in a real browser, the way the users will work with it. [Playwright](https://playwright.dev) drives Google Chrome through **7 flows and 60 checked steps**. It records **a video of every flow** and takes **a screenshot of every step**. Each step is shown as a caption at the bottom of the video and gets a ✔ when its check passes.

**Result: 60 / 60 steps passed, 7 / 7 flows passed** (run on 2026-11-02, Google Chrome, `http://127.0.0.1:8080`).

## Videos

| # | Video | What it shows | Logged in as | Length |
|---|---|---|---|---|
| 01 | [01_login_lockout.mp4](videos/01_login_lockout.mp4) | Log-in, wrong password, lock-out after 5 attempts, admin unlock | any / `w.anwar` | 24 s |
| 02 | [02_admin_registers_users_crud.mp4](videos/02_admin_registers_users_crud.mp4) | Add, edit, delete in every register and in User Accounts | `w.anwar` (Admin) | 76 s |
| 03 | [03_operator_call_flow.mp4](videos/03_operator_call_flow.mp4) | Log new call, assign technician, follow-up, open list, search | `a.khan` (Operator) | 26 s |
| 04 | [04_technician_resolve.mp4](videos/04_technician_resolve.mp4) | Technician resolves their own job | `u.tariq` (Technician) | 11 s |
| 05 | [05_manager_reports.mp4](videos/05_manager_reports.mp4) | Management reports R1-R6 on screen | `i.shah` (Manager) | 19 s |
| 06 | [06_maintenance.mp4](videos/06_maintenance.mp4) | Backup, restore (with date check), compact, integrity check, log | `a.khan`, `w.anwar` | 21 s |
| 07 | [07_access_control.mp4](videos/07_access_control.mp4) | Pages each role must not reach | all roles | 20 s |

The videos are MP4 (H.264) and play in any browser, in QuickTime, or in Windows Media Player.

## How to run it again

```bash
cd Part2_Development
./start.sh                    # the system must be running
./tests/e2e/run_e2e.sh        # asks for the MySQL root password
```

The script:

1. **saves the current database**, because the test adds and deletes records;
2. runs the 7 flows in Google Chrome and records the videos;
3. converts the videos to MP4 in `docs/videos/`, with screenshots in `docs/screenshots/e2e/`;
4. **puts the database back exactly as it was** and re-grants the role permissions. A database dump drops and re-creates the stored procedures, which removes their `EXECUTE` grants. The application's own Restore does the same re-grant (see [Testing](06_Testing.md), defects table).

Files: [`tests/e2e/flow.spec.js`](../tests/e2e/flow.spec.js) (the test), [`tests/e2e/playwright.config.js`](../tests/e2e/playwright.config.js), [`tests/e2e/run_e2e.sh`](../tests/e2e/run_e2e.sh). Raw results: `tests/e2e/results/steps.json`.

Test data is easy to recognise: *Test Office Murree*, *TEST-HW-0001*, *Test Analytics Suite*, *LIC-TEST-0001*, employee *E0901 Sara Test*, and user *s.test*.

## Flow by flow

### 01 Log-in and account lock-out

▶ Video: [01_login_lockout.mp4](videos/01_login_lockout.mp4)

Security of the log-in (NFR-02). The error message never says whether the username or the password was wrong. After 5 wrong passwords the account is locked until an administrator unlocks it.

| # | Step | Result | Screenshot |
|---|---|---|---|
| 1 | Wrong password shows a general error (does not say which part is wrong) | ✅ Pass | [view](screenshots/e2e/01_wrong_password_shows_a_general_error_does_not_say_.png) |
| 2 | Five wrong passwords lock the account of f.mehmood | ✅ Pass | [view](screenshots/e2e/02_five_wrong_passwords_lock_the_account_of_f_mehmood.png) |
| 3 | Even the correct password is refused while locked | ✅ Pass | [view](screenshots/e2e/03_even_the_correct_password_is_refused_while_locked.png) |
| 4 | Administrator w.anwar unlocks the account in User Accounts | ✅ Pass | [view](screenshots/e2e/04_administrator_w_anwar_unlocks_the_account_in_user_.png) |
| 5 | f.mehmood can log in again and reaches the Main Control Page | ✅ Pass | [view](screenshots/e2e/05_f_mehmood_can_log_in_again_and_reaches_the_main_co.png) |
| 6 | Log out ends the session | ✅ Pass | [view](screenshots/e2e/06_log_out_ends_the_session.png) |

### 02 Admin - registers and users (add, edit, delete)

▶ Video: [02_admin_registers_users_crud.mp4](videos/02_admin_registers_users_crud.mp4)

The System Administrator is the only role that can change the registers (access matrix, Table 40). Every register is tested with **add → edit → delete**. Where the design says records must be kept, the test checks that instead: employees get an end date and are not deleted, and an office that is still in use cannot be deleted. Validation is shown on two layers: the browser checks first, and the PHP check catches anything that gets past it.

| # | Step | Result | Screenshot |
|---|---|---|---|
| 7 | Office: add "Test Office Murree" | ✅ Pass | [view](screenshots/e2e/07_office_add_test_office_murree.png) |
| 8 | Office: server-side check rejects an invalid phone number | ✅ Pass | [view](screenshots/e2e/08_office_server_side_check_rejects_an_invalid_phone_.png) |
| 9 | Office: edit the phone number and save | ✅ Pass | [view](screenshots/e2e/09_office_edit_the_phone_number_and_save.png) |
| 10 | Office: delete "Test Office Murree" (after "Are you sure?") | ✅ Pass | [view](screenshots/e2e/10_office_delete_test_office_murree_after_are_you_sur.png) |
| 11 | Office in use (Lahore) cannot be deleted - clear message | ✅ Pass | [view](screenshots/e2e/11_office_in_use_lahore_cannot_be_deleted_clear_messa.png) |
| 12 | Hardware: add laptop TEST-HW-0001 | ✅ Pass | [view](screenshots/e2e/12_hardware_add_laptop_test_hw_0001.png) |
| 13 | Hardware: edit the model | ✅ Pass | [view](screenshots/e2e/13_hardware_edit_the_model.png) |
| 14 | Hardware: install licensed software on the laptop | ✅ Pass | [view](screenshots/e2e/14_hardware_install_licensed_software_on_the_laptop.png) |
| 15 | Hardware: remove the installed software | ✅ Pass | [view](screenshots/e2e/15_hardware_remove_the_installed_software.png) |
| 16 | Hardware: delete TEST-HW-0001 | ✅ Pass | [view](screenshots/e2e/16_hardware_delete_test_hw_0001.png) |
| 17 | Software: add "Test Analytics Suite 1.0" | ✅ Pass | [view](screenshots/e2e/17_software_add_test_analytics_suite_1_0.png) |
| 18 | Software: edit the version to 1.1 | ✅ Pass | [view](screenshots/e2e/18_software_edit_the_version_to_1_1.png) |
| 19 | Licence: add LIC-TEST-0001 (10 seats) for the new software | ✅ Pass | [view](screenshots/e2e/19_licence_add_lic_test_0001_10_seats_for_the_new_sof.png) |
| 20 | Licence: edit seats to 20 | ✅ Pass | [view](screenshots/e2e/20_licence_edit_seats_to_20.png) |
| 21 | Employee: add E0901 Sara Test | ✅ Pass | [view](screenshots/e2e/21_employee_add_e0901_sara_test.png) |
| 22 | Employee: edit the job title | ✅ Pass | [view](screenshots/e2e/22_employee_edit_the_job_title.png) |
| 23 | Technician: make Sara Test a technician with two skills | ✅ Pass | [view](screenshots/e2e/23_technician_make_sara_test_a_technician_with_two_sk.png) |
| 24 | Technician: edit - mark as on leave and add a third skill | ✅ Pass | [view](screenshots/e2e/24_technician_edit_mark_as_on_leave_and_add_a_third_s.png) |
| 25 | User: passwords that do not match are rejected | ✅ Pass | [view](screenshots/e2e/25_user_passwords_that_do_not_match_are_rejected.png) |
| 26 | User: create account s.test (Technician) | ✅ Pass | [view](screenshots/e2e/26_user_create_account_s_test_technician.png) |
| 27 | User: change the role of s.test to Helpdesk Operator | ✅ Pass | [view](screenshots/e2e/27_user_change_the_role_of_s_test_to_helpdesk_operato.png) |
| 28 | User: disable and then unlock s.test | ✅ Pass | [view](screenshots/e2e/28_user_disable_and_then_unlock_s_test.png) |
| 29 | New user s.test logs in with the password set by the admin | ✅ Pass | [view](screenshots/e2e/29_new_user_s_test_logs_in_with_the_password_set_by_t.png) |
| 30 | Employee leaves: enter an End Date (record is kept, not deleted) | ✅ Pass | [view](screenshots/e2e/30_employee_leaves_enter_an_end_date_record_is_kept_n.png) |

### 03 Operator - log, assign, follow-up and search a call

▶ Video: [03_operator_call_flow.mp4](videos/03_operator_call_flow.mp4)

The main helpdesk job (F2–F4). Choosing the caller and typing the serial number fill in the rest from the registers (UR-02–UR-04). The problem number is shown **before** saving and is the same after saving. The least-busy skilled technician is recommended, but the operator can choose another one.

| # | Step | Result | Screenshot |
|---|---|---|---|
| 31 | Main Control Page: operator buttons, reports locked | ✅ Pass | [view](screenshots/e2e/31_main_control_page_operator_buttons_reports_locked.png) |
| 32 | Log New Call: choosing the caller fills job title and office | ✅ Pass | [view](screenshots/e2e/32_log_new_call_choosing_the_caller_fills_job_title_a.png) |
| 33 | Typing the serial number fills equipment, warranty and installed software | ✅ Pass | [view](screenshots/e2e/33_typing_the_serial_number_fills_equipment_warranty_.png) |
| 34 | Problem details entered; the problem number is shown before saving | ✅ Pass | [view](screenshots/e2e/34_problem_details_entered_the_problem_number_is_show.png) |
| 35 | Save & Assign: same problem number confirmed after saving | ✅ Pass | [view](screenshots/e2e/35_save_assign_same_problem_number_confirmed_after_sa.png) |
| 36 | Assign Technician: operator chooses Usman Tariq | ✅ Pass | [view](screenshots/e2e/36_assign_technician_operator_chooses_usman_tariq.png) |
| 37 | Follow-up call: the caller rings again about the same problem | ✅ Pass | [view](screenshots/e2e/37_follow_up_call_the_caller_rings_again_about_the_sa.png) |
| 38 | Open Problems List: filter High priority, the new problem is listed | ✅ Pass | [view](screenshots/e2e/38_open_problems_list_filter_high_priority_the_new_pr.png) |
| 39 | Search Problem: full history with both calls | ✅ Pass | [view](screenshots/e2e/39_search_problem_full_history_with_both_calls.png) |

### 04 Technician - resolve own job

▶ Video: [04_technician_resolve.mp4](videos/04_technician_resolve.mp4)

The technician sees only their own open jobs and closes one (F5). Status is set to Closed by the stored procedure.

| # | Step | Result | Screenshot |
|---|---|---|---|
| 40 | My open jobs lists the new problem | ✅ Pass | [view](screenshots/e2e/40_my_open_jobs_lists_the_new_problem.png) |
| 41 | Resolve: time taken and resolution notes entered | ✅ Pass | [view](screenshots/e2e/41_resolve_time_taken_and_resolution_notes_entered.png) |
| 42 | Mark as Resolved: the problem is closed | ✅ Pass | [view](screenshots/e2e/42_mark_as_resolved_the_problem_is_closed.png) |

### 05 Manager - management reports R1 to R6

▶ Video: [05_manager_reports.mp4](videos/05_manager_reports.mp4)

The six management reports (Section 10) are opened on screen by the Office Manager.

| # | Step | Result | Screenshot |
|---|---|---|---|
| 43 | Report R1 shown on screen | ✅ Pass | [view](screenshots/e2e/43_report_r1_shown_on_screen.png) |
| 44 | Report R2 shown on screen | ✅ Pass | [view](screenshots/e2e/44_report_r2_shown_on_screen.png) |
| 45 | Report R3 shown on screen | ✅ Pass | [view](screenshots/e2e/45_report_r3_shown_on_screen.png) |
| 46 | Report R4 shown on screen | ✅ Pass | [view](screenshots/e2e/46_report_r4_shown_on_screen.png) |
| 47 | Report R5 shown on screen | ✅ Pass | [view](screenshots/e2e/47_report_r5_shown_on_screen.png) |
| 48 | Report R6 shown on screen | ✅ Pass | [view](screenshots/e2e/48_report_r6_shown_on_screen.png) |

### 06 Maintenance - backup, restore, compact and integrity check

▶ Video: [06_maintenance.mp4](videos/06_maintenance.mp4)

Database maintenance (Section 12). A restore needs the backup date typed in as confirmation; a wrong date cancels it. A safety backup is taken first. The integrity check is for the administrator only. Every action is written to the maintenance log.

| # | Step | Result | Screenshot |
|---|---|---|---|
| 49 | Operator: Back Up Now creates a backup file | ✅ Pass | [view](screenshots/e2e/49_operator_back_up_now_creates_a_backup_file.png) |
| 50 | Restore with the wrong confirmation date is cancelled | ✅ Pass | [view](screenshots/e2e/50_restore_with_the_wrong_confirmation_date_is_cancel.png) |
| 51 | Restore with the correct date restores the backup | ✅ Pass | [view](screenshots/e2e/51_restore_with_the_correct_date_restores_the_backup.png) |
| 52 | Compact Now optimises the tables | ✅ Pass | [view](screenshots/e2e/52_compact_now_optimises_the_tables.png) |
| 53 | Administrator: Run Integrity Check passes | ✅ Pass | [view](screenshots/e2e/53_administrator_run_integrity_check_passes.png) |
| 54 | Maintenance log records every action with user and time | ✅ Pass | [view](screenshots/e2e/54_maintenance_log_records_every_action_with_user_and.png) |

### 07 Access control - each role only sees its own functions

▶ Video: [07_access_control.mp4](videos/07_access_control.mp4)

Each role is refused pages outside its permissions with an "Access denied" page. A visitor who is not logged in is sent to the sign-in page.

| # | Step | Result | Screenshot |
|---|---|---|---|
| 55 | Operator cannot open Management Reports | ✅ Pass | [view](screenshots/e2e/55_operator_cannot_open_management_reports.png) |
| 56 | Operator cannot open User Accounts | ✅ Pass | [view](screenshots/e2e/56_operator_cannot_open_user_accounts.png) |
| 57 | Operator sees registers as view only (no Add button) | ✅ Pass | [view](screenshots/e2e/57_operator_sees_registers_as_view_only_no_add_button.png) |
| 58 | Technician cannot log a new call | ✅ Pass | [view](screenshots/e2e/58_technician_cannot_log_a_new_call.png) |
| 59 | Manager cannot assign technicians | ✅ Pass | [view](screenshots/e2e/59_manager_cannot_assign_technicians.png) |
| 60 | Opening a page without logging in goes to the Sign in page | ✅ Pass | [view](screenshots/e2e/60_opening_a_page_without_logging_in_goes_to_the_sign.png) |

## Problems found while building this test

| Problem | Cause | Fix |
|---|---|---|
| Technician could not resolve: “Your role does not have permission to do this.” | Not an application fault. The test's own database reset used a plain `mysql < dump.sql`, which re-creates the procedures and so removes their `EXECUTE` grants. | `run_e2e.sh` runs `04_security_roles.sql` after putting the database back. The application's Restore already re-grants these rights. |
| Playwright could not record video on macOS 12 | Playwright's own ffmpeg build does not support macOS 12. | `run_e2e.sh` points Playwright at the system ffmpeg (`brew install ffmpeg`). |
