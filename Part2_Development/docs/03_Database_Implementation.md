# 03 – Database Implementation (MySQL)

This document shows how the Part 1 design (ERD, data dictionary, Section 7) was built in MySQL 8.0.

## 1. Tables (16)

All tables use InnoDB (for foreign keys and transactions) and utf8mb4.

| Table | Primary key | Main foreign keys | Key constraints |
|---|---|---|---|
| Department | DepartmentID | – | UNIQUE name |
| Office | OfficeID | – | UNIQUE name; phone pattern; Specialisation IN (7 values) |
| Employee | EmployeeID `E0045` | OfficeID, DepartmentID | ID pattern `^E[0-9]{4}$`; name, phone and email patterns; UNIQUE email; EndDate ≥ StartDate |
| AppUser | UserID | EmployeeID (UNIQUE) | Username 4–30; PasswordHash must be a 60-char bcrypt hash; Role IN (4); FailedLogins 0–5 |
| Technician | TechnicianID | EmployeeID (UNIQUE) | 1:1 with Employee |
| ProblemType | ProblemTypeID | – | FaultCategory IN (Hardware, Software) |
| TechnicianSpecialism | (TechnicianID, ProblemTypeID) | both | link table for Technician M:N ProblemType |
| Manufacturer | ManufacturerID | – | UNIQUE name; website must start `http(s)://` |
| EquipmentType | EquipmentTypeID | – | HardwareCategory IN (Office, Network) |
| Hardware | HardwareID | EquipmentTypeID, ManufacturerID, OfficeID, AssignedToEmployeeID | UNIQUE serial; serial pattern; WarrantyExpiry ≥ PurchaseDate |
| Software | SoftwareID | ManufacturerID | UNIQUE (name, version); category IN (6) |
| SoftwareLicence | LicenceID | SoftwareID | UNIQUE number; Seats 1–10,000; Expiry ≥ Start (NULL = perpetual) |
| InstalledSoftware | (HardwareID, LicenceID) | both | link table for Hardware M:N SoftwareLicence |
| Problem | ProblemNumber (AUTO_INCREMENT) | Reporter, ProblemType, Hardware, OS, Software, Technician, ResolvedBy | Description ≥ 10 chars; Priority IN (4); `chk_hours` 0.25–500; `chk_resolved` Resolved > Logged; `chk_closed` |
| HelpdeskCall | CallID | ProblemNumber, Caller, Operator | CallType IN (New, Follow-up); CallReason ≥ 3 chars; Urgency IN (3) |
| MaintenanceLog | LogID | UserID (NULL = scheduled backup) | ActionType IN (Backup, Restore, Compact, Check); Outcome IN (Success, Failed); only a Backup may have no user |

**Indexes** (Design Doc 7.4):
* `idx_emp_name` (LastName, FirstName) for the caller search
* `idx_problem_status` (Status, TechnicianID) for the workload count
* `idx_problem_logged` and `idx_call_datetime` for the monthly reports
* UNIQUE indexes on SerialNumber, LicenceNumber, Email and Username

## 2. Views (6)

| View | Used by | What it adds |
|---|---|---|
| `vw_TechnicianWorkload` | Assign Technician | Each technician × specialism with **OpenJobs** count (UR-08) |
| `vw_HardwareStatus` | Log Call, Hardware register, R4 | **WarrantyStatus** = Under warranty / Expired (UR-03) |
| `vw_LicenceStatus` | Software register, R4 | **LicenceStatus** = Valid / Expired, seats used (UR-04) |
| `vw_ProblemDetail` | Most screens, R1/R5/R6 | Problem joined to 10 tables, `P-000128` reference, call count |
| `vw_CallDetail` | Problem details, Follow-up | Caller and operator names **without exposing AppUser** (password hashes) |
| `vw_MaintenanceLog` | Maintenance screen | Who did each backup/restore/compact |

## 3. Triggers (7)

MySQL CHECK constraints cannot use `CURRENT_DATE` or look at other tables, so these rules are enforced by triggers instead:

| Trigger | Rule |
|---|---|
| `trg_Problem_BeforeInsert` | Reporter must be employed on the logged date (BR-04). Assigned technician must have the specialism (FR-09). Sets DateTimeAssigned. Resolved time cannot be in the future |
| `trg_Problem_BeforeUpdate` | Re-checks the specialism when the technician or the type changes. Stamps DateTimeAssigned on first assignment. No future resolved time |
| `trg_HelpdeskCall_BeforeInsert` | The logger must have the **Operator** role. The caller must be employed on the call date |
| `trg_Employee_BeforeInsert/Update` | StartDate not in the future |
| `trg_Hardware_BeforeInsert/Update` | PurchaseDate not in the future |

## 4. Stored procedures (13)

| Procedure | Purpose |
|---|---|
| `sp_LogNewProblem` | Inserts the Problem **and** its first call in one transaction. Any error → ROLLBACK (NFR-04) |
| `sp_LogFollowUpCall` | Adds a follow-up call. Rejects unknown problem numbers |
| `sp_GetSkilledTechnicians` | Skilled technicians only, available first, then fewest open jobs |
| `sp_AssignTechnician` | Assigns a technician to an open problem (the trigger checks the skill) |
| `sp_ResolveProblem` | Checks the role, own job, notes, hours and dates, then sets Status = Closed |
| `sp_LogMaintenance` | Writes MaintenanceLog. Refuses Restore/Compact for non-operators and Check for non-admins; accepts a scheduled backup without a user |
| `sp_CompactDatabase` | OPTIMIZE TABLE on all 16 tables (operators only) |
| `sp_Report_R1` … `sp_Report_R6` | The six management reports (Section 10 of the design) |

All views, triggers and procedures are owned by `ml_owner`, a locked account that nobody can log in with. Restore can then recreate them without needing root-level rights.

## 5. Pilot data (`03_pilot_data.sql`)

| Table | Rows | Notes |
|---|---|---|
| Office / Department | 15 / 8 | all 7 specialisations used |
| Employee | 60 | 4 per office: 5 ex-employees, 5 operators, 6 technicians, 3 managers, 1 admin |
| AppUser | 15 | one locked account (`r.aslam`) |
| Technician / TechnicianSpecialism | 6 / 18 | overlapping skills; Kamran Javed on leave |
| ProblemType | 14 | 10 hardware, 4 software |
| Manufacturer / EquipmentType | 10 / 10 | |
| Hardware | 40 | office and network kit; some warranties expired |
| Software / SoftwareLicence / InstalledSoftware | 8 / 12 / 50 | expired, perpetual, volume and OEM licences |
| Problem / HelpdeskCall | 60 / 90 | Aug–Oct 2026, open and closed, 30 follow-ups |
| MaintenanceLog | 8 | includes one scheduled backup and one integrity check |

Some cases are built in on purpose so that the reports have something to find:

* The Epson printer `EP-L3150-0042` (warranty expired) has many jobs, so R4 says **Consider replacing**.
* The HP laptop `HP-5CG1234XYZ` (under warranty) has repeat jobs, so R4 says **Warranty claim**.
* PowerPoint and Zoho Projects have only expired licences, so R4 says **Renew licence**.
* Ex-employee Hassan Raza has a problem from before he left, showing that history is kept.

## 6. Changes from the Part 1 design

| Design | Implementation | Reason |
|---|---|---|
| “Not in the future” listed as CHECK rules | Triggers | MySQL CHECK cannot use `CURRENT_DATE` |
| 2 triggers planned | 7 triggers | Extra rules (operator-only call logging, future dates) |
| 4 views planned | 6 views | `vw_CallDetail` and `vw_MaintenanceLog` keep password hashes hidden from other roles |
| Operators `GRANT SELECT ON mlhelpdesk.*` | SELECT granted table by table, without AppUser | Tighter least privilege (weakness noted in Design Doc 13.3) |
| – | `ml_owner` definer account, `log_bin_trust_function_creators = ON` | Needed so that Restore works without root privileges |
| MaintenanceLog.UserID NOT NULL | NULL allowed, but only for `Backup` | The nightly scheduled backup has no logged-in user |
| Integrity check (Table 41) | Run from PHP with the maintenance account, not as a procedure | MySQL does not allow CHECK TABLE inside stored procedures |
