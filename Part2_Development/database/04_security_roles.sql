-- =====================================================================
-- ML IT Helpdesk System  |  Script 04 of 06: database-level security
-- Implements Section 11.3 of the Design Document (least privilege).
-- The PHP application connects with a different MySQL account for each
-- application role, so the brief's rules hold even if the app is bypassed:
--   * operators can log jobs but have NO execute right on report procedures
--   * managers can run reports but have NO insert right on Problem/HelpdeskCall
-- Run as root after 03_pilot_data.sql.
-- Passwords below are for the pilot only - change them before go-live and
-- keep them in Part2_Database/app/config/db.php.
-- =====================================================================
USE mlhelpdesk;

-- Re-runnable: remove old accounts and roles first
DROP USER IF EXISTS 'ml_auth'@'localhost', 'ml_operator'@'localhost',
                    'ml_technician'@'localhost', 'ml_manager'@'localhost',
                    'ml_admin'@'localhost', 'ml_backup'@'localhost',
                    'ml_maint'@'localhost';
DROP ROLE IF EXISTS 'r_operator', 'r_technician', 'r_manager', 'r_admin';

CREATE ROLE 'r_operator', 'r_technician', 'r_manager', 'r_admin';

-- ---------------------------------------------------------------------
-- r_operator: read registers, log / assign / resolve jobs, restore and
-- compact. No access to the report procedures (UR-13, UR-16).
-- ---------------------------------------------------------------------
GRANT SELECT ON mlhelpdesk.Office               TO 'r_operator';
GRANT SELECT ON mlhelpdesk.Department           TO 'r_operator';
GRANT SELECT ON mlhelpdesk.Employee             TO 'r_operator';
GRANT SELECT ON mlhelpdesk.Technician           TO 'r_operator';
GRANT SELECT ON mlhelpdesk.TechnicianSpecialism TO 'r_operator';
GRANT SELECT ON mlhelpdesk.ProblemType          TO 'r_operator';
GRANT SELECT ON mlhelpdesk.Manufacturer         TO 'r_operator';
GRANT SELECT ON mlhelpdesk.EquipmentType        TO 'r_operator';
GRANT SELECT ON mlhelpdesk.Hardware             TO 'r_operator';
GRANT SELECT ON mlhelpdesk.Software             TO 'r_operator';
GRANT SELECT ON mlhelpdesk.SoftwareLicence      TO 'r_operator';
GRANT SELECT ON mlhelpdesk.InstalledSoftware    TO 'r_operator';
GRANT SELECT ON mlhelpdesk.MaintenanceLog       TO 'r_operator';
GRANT SELECT, INSERT, UPDATE ON mlhelpdesk.Problem      TO 'r_operator';
GRANT SELECT, INSERT         ON mlhelpdesk.HelpdeskCall TO 'r_operator';
GRANT SELECT ON mlhelpdesk.vw_TechnicianWorkload TO 'r_operator';
GRANT SELECT ON mlhelpdesk.vw_HardwareStatus     TO 'r_operator';
GRANT SELECT ON mlhelpdesk.vw_LicenceStatus      TO 'r_operator';
GRANT SELECT ON mlhelpdesk.vw_ProblemDetail      TO 'r_operator';
GRANT SELECT ON mlhelpdesk.vw_CallDetail     TO 'r_operator';
GRANT SELECT ON mlhelpdesk.vw_MaintenanceLog TO 'r_operator';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_LogNewProblem         TO 'r_operator';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_LogFollowUpCall       TO 'r_operator';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_GetSkilledTechnicians TO 'r_operator';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_AssignTechnician      TO 'r_operator';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_ResolveProblem        TO 'r_operator';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_LogMaintenance        TO 'r_operator';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_CompactDatabase       TO 'r_operator';

-- ---------------------------------------------------------------------
-- r_technician: read jobs and registers; resolve own jobs through the
-- stored procedure (which checks ownership); back up.
-- ---------------------------------------------------------------------
GRANT SELECT ON mlhelpdesk.Office               TO 'r_technician';
GRANT SELECT ON mlhelpdesk.Department           TO 'r_technician';
GRANT SELECT ON mlhelpdesk.Employee             TO 'r_technician';
GRANT SELECT ON mlhelpdesk.Technician           TO 'r_technician';
GRANT SELECT ON mlhelpdesk.TechnicianSpecialism TO 'r_technician';
GRANT SELECT ON mlhelpdesk.ProblemType          TO 'r_technician';
GRANT SELECT ON mlhelpdesk.Manufacturer         TO 'r_technician';
GRANT SELECT ON mlhelpdesk.EquipmentType        TO 'r_technician';
GRANT SELECT ON mlhelpdesk.Hardware             TO 'r_technician';
GRANT SELECT ON mlhelpdesk.Software             TO 'r_technician';
GRANT SELECT ON mlhelpdesk.SoftwareLicence      TO 'r_technician';
GRANT SELECT ON mlhelpdesk.InstalledSoftware    TO 'r_technician';
GRANT SELECT ON mlhelpdesk.Problem              TO 'r_technician';
GRANT SELECT ON mlhelpdesk.HelpdeskCall         TO 'r_technician';
GRANT SELECT ON mlhelpdesk.MaintenanceLog       TO 'r_technician';
GRANT SELECT ON mlhelpdesk.vw_ProblemDetail     TO 'r_technician';
GRANT SELECT ON mlhelpdesk.vw_CallDetail     TO 'r_technician';
GRANT SELECT ON mlhelpdesk.vw_MaintenanceLog TO 'r_technician';
GRANT SELECT ON mlhelpdesk.vw_HardwareStatus    TO 'r_technician';
GRANT SELECT ON mlhelpdesk.vw_LicenceStatus     TO 'r_technician';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_ResolveProblem TO 'r_technician';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_LogMaintenance TO 'r_technician';

-- ---------------------------------------------------------------------
-- r_manager: read-only; runs the six reports; back up. No INSERT on
-- Problem or HelpdeskCall, so a manager cannot log a job (UR-14).
-- ---------------------------------------------------------------------
GRANT SELECT ON mlhelpdesk.Office               TO 'r_manager';
GRANT SELECT ON mlhelpdesk.Department           TO 'r_manager';
GRANT SELECT ON mlhelpdesk.Employee             TO 'r_manager';
GRANT SELECT ON mlhelpdesk.Technician           TO 'r_manager';
GRANT SELECT ON mlhelpdesk.TechnicianSpecialism TO 'r_manager';
GRANT SELECT ON mlhelpdesk.ProblemType          TO 'r_manager';
GRANT SELECT ON mlhelpdesk.Manufacturer         TO 'r_manager';
GRANT SELECT ON mlhelpdesk.EquipmentType        TO 'r_manager';
GRANT SELECT ON mlhelpdesk.Hardware             TO 'r_manager';
GRANT SELECT ON mlhelpdesk.Software             TO 'r_manager';
GRANT SELECT ON mlhelpdesk.SoftwareLicence      TO 'r_manager';
GRANT SELECT ON mlhelpdesk.InstalledSoftware    TO 'r_manager';
GRANT SELECT ON mlhelpdesk.Problem              TO 'r_manager';
GRANT SELECT ON mlhelpdesk.HelpdeskCall         TO 'r_manager';
GRANT SELECT ON mlhelpdesk.MaintenanceLog       TO 'r_manager';
GRANT SELECT ON mlhelpdesk.vw_ProblemDetail     TO 'r_manager';
GRANT SELECT ON mlhelpdesk.vw_CallDetail     TO 'r_manager';
GRANT SELECT ON mlhelpdesk.vw_MaintenanceLog TO 'r_manager';
GRANT SELECT ON mlhelpdesk.vw_HardwareStatus    TO 'r_manager';
GRANT SELECT ON mlhelpdesk.vw_LicenceStatus     TO 'r_manager';
GRANT SELECT ON mlhelpdesk.vw_TechnicianWorkload TO 'r_manager';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_Report_R1 TO 'r_manager';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_Report_R2 TO 'r_manager';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_Report_R3 TO 'r_manager';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_Report_R4 TO 'r_manager';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_Report_R5 TO 'r_manager';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_Report_R6 TO 'r_manager';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_LogMaintenance TO 'r_manager';

-- ---------------------------------------------------------------------
-- r_admin: maintains the registers and user accounts; back up.
-- ---------------------------------------------------------------------
GRANT SELECT, INSERT, UPDATE, DELETE ON mlhelpdesk.Office               TO 'r_admin';
GRANT SELECT, INSERT, UPDATE, DELETE ON mlhelpdesk.Department           TO 'r_admin';
GRANT SELECT, INSERT, UPDATE, DELETE ON mlhelpdesk.Employee             TO 'r_admin';
GRANT SELECT, INSERT, UPDATE, DELETE ON mlhelpdesk.Technician           TO 'r_admin';
GRANT SELECT, INSERT, UPDATE, DELETE ON mlhelpdesk.TechnicianSpecialism TO 'r_admin';
GRANT SELECT, INSERT, UPDATE, DELETE ON mlhelpdesk.ProblemType          TO 'r_admin';
GRANT SELECT, INSERT, UPDATE, DELETE ON mlhelpdesk.Manufacturer         TO 'r_admin';
GRANT SELECT, INSERT, UPDATE, DELETE ON mlhelpdesk.EquipmentType        TO 'r_admin';
GRANT SELECT, INSERT, UPDATE, DELETE ON mlhelpdesk.Hardware             TO 'r_admin';
GRANT SELECT, INSERT, UPDATE, DELETE ON mlhelpdesk.Software             TO 'r_admin';
GRANT SELECT, INSERT, UPDATE, DELETE ON mlhelpdesk.SoftwareLicence      TO 'r_admin';
GRANT SELECT, INSERT, UPDATE, DELETE ON mlhelpdesk.InstalledSoftware    TO 'r_admin';
GRANT SELECT, INSERT, UPDATE         ON mlhelpdesk.AppUser              TO 'r_admin';
GRANT SELECT ON mlhelpdesk.Problem          TO 'r_admin';
GRANT SELECT ON mlhelpdesk.HelpdeskCall     TO 'r_admin';
GRANT SELECT ON mlhelpdesk.MaintenanceLog   TO 'r_admin';
GRANT SELECT ON mlhelpdesk.vw_ProblemDetail TO 'r_admin';
GRANT SELECT ON mlhelpdesk.vw_CallDetail     TO 'r_admin';
GRANT SELECT ON mlhelpdesk.vw_MaintenanceLog TO 'r_admin';
GRANT SELECT ON mlhelpdesk.vw_HardwareStatus TO 'r_admin';
GRANT SELECT ON mlhelpdesk.vw_LicenceStatus TO 'r_admin';
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_LogMaintenance TO 'r_admin';

-- ---------------------------------------------------------------------
-- Login accounts used by the PHP application
-- ---------------------------------------------------------------------
CREATE USER 'ml_operator'@'localhost'   IDENTIFIED BY 'Op#Pilot2026!';
CREATE USER 'ml_technician'@'localhost' IDENTIFIED BY 'Tech#Pilot2026!';
CREATE USER 'ml_manager'@'localhost'    IDENTIFIED BY 'Mgr#Pilot2026!';
CREATE USER 'ml_admin'@'localhost'      IDENTIFIED BY 'Adm#Pilot2026!';
GRANT 'r_operator'   TO 'ml_operator'@'localhost';
GRANT 'r_technician' TO 'ml_technician'@'localhost';
GRANT 'r_manager'    TO 'ml_manager'@'localhost';
GRANT 'r_admin'      TO 'ml_admin'@'localhost';
SET DEFAULT ROLE ALL TO 'ml_operator'@'localhost', 'ml_technician'@'localhost',
                        'ml_manager'@'localhost',  'ml_admin'@'localhost';

-- Used only by the log-in page: read accounts, update the lock-out counter.
CREATE USER 'ml_auth'@'localhost' IDENTIFIED BY 'Auth#Pilot2026!';
GRANT SELECT ON mlhelpdesk.AppUser  TO 'ml_auth'@'localhost';
GRANT SELECT ON mlhelpdesk.Employee TO 'ml_auth'@'localhost';
GRANT UPDATE (FailedLogins, IsActive) ON mlhelpdesk.AppUser TO 'ml_auth'@'localhost';

-- Used by "Back Up Database Now" (all users): read-only dump rights.
CREATE USER 'ml_backup'@'localhost' IDENTIFIED BY 'Bak#Pilot2026!';
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES, EVENT ON mlhelpdesk.* TO 'ml_backup'@'localhost';
GRANT SHOW_ROUTINE ON *.* TO 'ml_backup'@'localhost';
-- the scheduled nightly backup logs itself (no user logged in)
GRANT EXECUTE ON PROCEDURE mlhelpdesk.sp_LogMaintenance TO 'ml_backup'@'localhost';

-- Used by Restore and Compact (Helpdesk Operators only - checked by the app
-- before this account is used, and by sp_LogMaintenance / sp_CompactDatabase).
CREATE USER 'ml_maint'@'localhost' IDENTIFIED BY 'Mnt#Pilot2026!';
GRANT ALL PRIVILEGES ON mlhelpdesk.* TO 'ml_maint'@'localhost';

-- With binary logging on, MySQL only lets SUPER users create triggers and
-- routines. This lets the restore account rebuild them from a backup
-- without being given SUPER (least privilege). Saved across restarts.
SET PERSIST log_bin_trust_function_creators = ON;

FLUSH PRIVILEGES;

-- Quick check
SHOW GRANTS FOR 'ml_operator'@'localhost' USING 'r_operator';
SHOW GRANTS FOR 'ml_manager'@'localhost'  USING 'r_manager';
