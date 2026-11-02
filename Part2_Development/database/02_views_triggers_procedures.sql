-- =====================================================================
-- ML IT Helpdesk System  |  Script 02 of 06: views, triggers, procedures
-- Implements Section 7.3 (Table 29) and the business rules BR-04, BR-11,
-- BR-12 and FR-09 of the Design Document.
-- Run after 01_create_tables.sql.
-- =====================================================================
USE mlhelpdesk;

-- =====================================================================
-- VIEWS
-- =====================================================================

-- Technicians per skill area with their count of Open problems (UR-08).
-- Use: SELECT ... WHERE ProblemTypeID = ? ORDER BY IsAvailable DESC, OpenJobs;
CREATE OR REPLACE DEFINER = 'ml_owner'@'localhost' SQL SECURITY DEFINER VIEW vw_TechnicianWorkload AS
SELECT t.TechnicianID,
       CONCAT(e.FirstName, ' ', e.LastName)            AS TechnicianName,
       t.IsAvailable,
       ts.ProblemTypeID,
       pt.TypeName                                       AS ProblemType,
       (SELECT GROUP_CONCAT(pt2.TypeName ORDER BY pt2.TypeName SEPARATOR ', ')
          FROM TechnicianSpecialism ts2
          JOIN ProblemType pt2 ON pt2.ProblemTypeID = ts2.ProblemTypeID
         WHERE ts2.TechnicianID = t.TechnicianID)        AS Specialisms,
       (SELECT COUNT(*)
          FROM Problem p
         WHERE p.TechnicianID = t.TechnicianID
           AND p.Status = 'Open')                        AS OpenJobs
FROM Technician t
JOIN Employee e              ON e.EmployeeID     = t.EmployeeID
JOIN TechnicianSpecialism ts ON ts.TechnicianID  = t.TechnicianID
JOIN ProblemType pt          ON pt.ProblemTypeID = ts.ProblemTypeID;

-- Hardware register with calculated warranty status (UR-03, FR-05).
CREATE OR REPLACE DEFINER = 'ml_owner'@'localhost' SQL SECURITY DEFINER VIEW vw_HardwareStatus AS
SELECT h.HardwareID, h.SerialNumber,
       et.TypeName AS EquipmentType, et.HardwareCategory,
       m.ManufacturerName, h.Make, h.Model,
       h.PurchaseDate, h.WarrantyExpiry,
       CASE WHEN h.WarrantyExpiry >= CURRENT_DATE
            THEN 'Under warranty' ELSE 'Expired' END AS WarrantyStatus,
       o.OfficeID, o.OfficeName,
       h.AssignedToEmployeeID,
       CONCAT(e.FirstName, ' ', e.LastName) AS AssignedTo
FROM Hardware h
JOIN EquipmentType et ON et.EquipmentTypeID = h.EquipmentTypeID
JOIN Manufacturer m   ON m.ManufacturerID   = h.ManufacturerID
JOIN Office o         ON o.OfficeID         = h.OfficeID
LEFT JOIN Employee e  ON e.EmployeeID       = h.AssignedToEmployeeID;

-- Licences with calculated status and seats used (UR-04, FR-06).
CREATE OR REPLACE DEFINER = 'ml_owner'@'localhost' SQL SECURITY DEFINER VIEW vw_LicenceStatus AS
SELECT l.LicenceID, l.LicenceNumber,
       s.SoftwareID, s.SoftwareName, s.Version, s.SoftwareCategory,
       l.LicenceType, l.StartDate, l.ExpiryDate,
       CASE WHEN l.ExpiryDate IS NULL OR l.ExpiryDate >= CURRENT_DATE
            THEN 'Valid' ELSE 'Expired' END AS LicenceStatus,
       l.Seats,
       (SELECT COUNT(*) FROM InstalledSoftware i
         WHERE i.LicenceID = l.LicenceID) AS SeatsUsed
FROM SoftwareLicence l
JOIN Software s ON s.SoftwareID = l.SoftwareID;

-- One row per problem with everything the screens and reports need.
-- The office of a problem is the office of the employee who reported it.
CREATE OR REPLACE DEFINER = 'ml_owner'@'localhost' SQL SECURITY DEFINER VIEW vw_ProblemDetail AS
SELECT p.ProblemNumber,
       CONCAT('P-', LPAD(p.ProblemNumber, 6, '0'))  AS ProblemRef,
       p.DateTimeLogged, p.Priority, p.Status, p.Description,
       pt.ProblemTypeID, pt.TypeName                AS ProblemType, pt.FaultCategory,
       r.EmployeeID                                 AS ReporterID,
       CONCAT(r.FirstName, ' ', r.LastName)         AS ReporterName,
       o.OfficeID, o.OfficeName, o.Specialisation   AS OfficeSpecialisation,
       h.HardwareID, h.SerialNumber,
       et.TypeName                                  AS EquipmentType,
       CONCAT(m.ManufacturerName, ' ', h.Make, ' ', h.Model) AS EquipmentName,
       os.SoftwareID                                AS OSSoftwareID,
       CONCAT(os.SoftwareName, ' ', os.Version)     AS OperatingSystem,
       sw.SoftwareID,
       CONCAT(sw.SoftwareName, ' ', sw.Version)     AS Application,
       t.TechnicianID,
       CONCAT(te.FirstName, ' ', te.LastName)       AS TechnicianName,
       p.DateTimeAssigned, p.DateTimeResolved, p.ResolutionNotes, p.TimeTakenHours,
       (SELECT COUNT(*) FROM HelpdeskCall c
         WHERE c.ProblemNumber = p.ProblemNumber)   AS CallCount
FROM Problem p
JOIN ProblemType pt     ON pt.ProblemTypeID  = p.ProblemTypeID
JOIN Employee r         ON r.EmployeeID      = p.ReportedByEmployeeID
JOIN Office o           ON o.OfficeID        = r.OfficeID
LEFT JOIN Hardware h       ON h.HardwareID      = p.HardwareID
LEFT JOIN EquipmentType et ON et.EquipmentTypeID = h.EquipmentTypeID
LEFT JOIN Manufacturer m   ON m.ManufacturerID  = h.ManufacturerID
LEFT JOIN Software os      ON os.SoftwareID     = p.OSSoftwareID
LEFT JOIN Software sw      ON sw.SoftwareID     = p.SoftwareID
LEFT JOIN Technician t     ON t.TechnicianID    = p.TechnicianID
LEFT JOIN Employee te      ON te.EmployeeID     = t.EmployeeID;


-- Call history with caller and operator names. Lets every role see who
-- logged a call without being granted SELECT on AppUser (password hashes).
CREATE OR REPLACE DEFINER = 'ml_owner'@'localhost' SQL SECURITY DEFINER VIEW vw_CallDetail AS
SELECT c.CallID, c.ProblemNumber, c.CallDateTime, c.CallType,
       c.CallReason, c.UrgencyLevel, c.Notes,
       c.CallerEmployeeID,
       CONCAT(ce.FirstName, ' ', ce.LastName) AS CallerName,
       c.OperatorUserID,
       CONCAT(oe.FirstName, ' ', oe.LastName) AS OperatorName
FROM HelpdeskCall c
JOIN Employee ce ON ce.EmployeeID = c.CallerEmployeeID
JOIN AppUser u   ON u.UserID      = c.OperatorUserID
JOIN Employee oe ON oe.EmployeeID = u.EmployeeID;

-- Maintenance log with the name and role of the user (no password data).
-- Rows without a user are the scheduled nightly backups.
CREATE OR REPLACE DEFINER = 'ml_owner'@'localhost' SQL SECURITY DEFINER VIEW vw_MaintenanceLog AS
SELECT m.LogID, m.ActionDateTime, m.ActionType, m.BackupFile, m.Outcome,
       m.UserID, u.Username,
       COALESCE(u.Role, 'System') AS Role,
       COALESCE(CONCAT(e.FirstName, ' ', e.LastName), 'Scheduled backup') AS FullName
FROM MaintenanceLog m
LEFT JOIN AppUser u  ON u.UserID     = m.UserID
LEFT JOIN Employee e ON e.EmployeeID = u.EmployeeID;


-- =====================================================================
-- TRIGGERS
-- MySQL CHECK constraints cannot use CURRENT_DATE / NOW(), so the
-- "not in the future" rules and cross-table rules are enforced here.
-- =====================================================================
DELIMITER $$

-- Employee: StartDate must not be in the future.
CREATE DEFINER = 'ml_owner'@'localhost' TRIGGER trg_Employee_BeforeInsert
BEFORE INSERT ON Employee FOR EACH ROW
BEGIN
  IF NEW.StartDate > CURRENT_DATE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Start date cannot be in the future.';
  END IF;
END$$

CREATE DEFINER = 'ml_owner'@'localhost' TRIGGER trg_Employee_BeforeUpdate
BEFORE UPDATE ON Employee FOR EACH ROW
BEGIN
  IF NEW.StartDate > CURRENT_DATE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Start date cannot be in the future.';
  END IF;
END$$

-- Hardware: PurchaseDate must not be in the future.
CREATE DEFINER = 'ml_owner'@'localhost' TRIGGER trg_Hardware_BeforeInsert
BEFORE INSERT ON Hardware FOR EACH ROW
BEGIN
  IF NEW.PurchaseDate > CURRENT_DATE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Purchase date cannot be in the future.';
  END IF;
END$$

CREATE DEFINER = 'ml_owner'@'localhost' TRIGGER trg_Hardware_BeforeUpdate
BEFORE UPDATE ON Hardware FOR EACH ROW
BEGIN
  IF NEW.PurchaseDate > CURRENT_DATE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Purchase date cannot be in the future.';
  END IF;
END$$

-- Problem insert: reporter must be employed when the problem is logged (BR-04);
-- an assigned technician must have the specialism (BR-11, FR-09).
CREATE DEFINER = 'ml_owner'@'localhost' TRIGGER trg_Problem_BeforeInsert
BEFORE INSERT ON Problem FOR EACH ROW
BEGIN
  DECLARE v_logged DATE;
  SET v_logged = DATE(COALESCE(NEW.DateTimeLogged, NOW()));

  IF NOT EXISTS (SELECT 1 FROM Employee e
                  WHERE e.EmployeeID = NEW.ReportedByEmployeeID
                    AND e.StartDate <= v_logged
                    AND (e.EndDate IS NULL OR e.EndDate >= v_logged)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Caller not found or no longer employed.';
  END IF;

  IF NEW.TechnicianID IS NOT NULL THEN
    IF NOT EXISTS (SELECT 1 FROM TechnicianSpecialism ts
                    WHERE ts.TechnicianID  = NEW.TechnicianID
                      AND ts.ProblemTypeID = NEW.ProblemTypeID) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This technician is not skilled in this problem type.';
    END IF;
    IF NEW.DateTimeAssigned IS NULL THEN
      SET NEW.DateTimeAssigned = NOW();
    END IF;
  END IF;

  IF NEW.DateTimeResolved > NOW() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Resolved time cannot be in the future.';
  END IF;
END$$

-- Problem update: re-check the specialism whenever the technician or the
-- problem type changes; stamp DateTimeAssigned on first assignment.
CREATE DEFINER = 'ml_owner'@'localhost' TRIGGER trg_Problem_BeforeUpdate
BEFORE UPDATE ON Problem FOR EACH ROW
BEGIN
  IF NEW.TechnicianID IS NOT NULL
     AND (NOT (NEW.TechnicianID <=> OLD.TechnicianID)
          OR NEW.ProblemTypeID <> OLD.ProblemTypeID) THEN
    IF NOT EXISTS (SELECT 1 FROM TechnicianSpecialism ts
                    WHERE ts.TechnicianID  = NEW.TechnicianID
                      AND ts.ProblemTypeID = NEW.ProblemTypeID) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This technician is not skilled in this problem type.';
    END IF;
    IF OLD.TechnicianID IS NULL AND NEW.DateTimeAssigned IS NULL THEN
      SET NEW.DateTimeAssigned = NOW();
    END IF;
  END IF;

  IF NEW.DateTimeResolved > NOW() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Resolved time cannot be in the future.';
  END IF;
END$$

-- HelpdeskCall: the logger must be an Operator account and the caller must
-- be employed on the date of the call (BR-04, BR-10).
CREATE DEFINER = 'ml_owner'@'localhost' TRIGGER trg_HelpdeskCall_BeforeInsert
BEFORE INSERT ON HelpdeskCall FOR EACH ROW
BEGIN
  DECLARE v_call DATE;
  SET v_call = DATE(COALESCE(NEW.CallDateTime, NOW()));

  IF NOT EXISTS (SELECT 1 FROM AppUser u
                  WHERE u.UserID = NEW.OperatorUserID
                    AND u.Role = 'Operator') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only helpdesk operators can log calls.';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM Employee e
                  WHERE e.EmployeeID = NEW.CallerEmployeeID
                    AND e.StartDate <= v_call
                    AND (e.EndDate IS NULL OR e.EndDate >= v_call)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Caller not found or no longer employed.';
  END IF;
END$$


-- =====================================================================
-- STORED PROCEDURES - helpdesk jobs
-- =====================================================================

-- Logs a new problem and its first call in ONE transaction (UR-05, NFR-04).
CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_LogNewProblem(
  IN  p_ReporterID     CHAR(5),
  IN  p_OperatorUserID INT,
  IN  p_ProblemTypeID  INT,
  IN  p_HardwareID     INT,
  IN  p_OSSoftwareID   INT,
  IN  p_SoftwareID     INT,
  IN  p_Description    TEXT,
  IN  p_Priority       VARCHAR(8),
  IN  p_CallReason     VARCHAR(255),
  IN  p_UrgencyLevel   VARCHAR(10),
  OUT p_ProblemNumber  INT)
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;
    INSERT INTO Problem (ReportedByEmployeeID, ProblemTypeID, HardwareID,
                         OSSoftwareID, SoftwareID, Description, Priority)
    VALUES (p_ReporterID, p_ProblemTypeID, p_HardwareID,
            p_OSSoftwareID, p_SoftwareID, p_Description,
            COALESCE(p_Priority, 'Medium'));
    SET p_ProblemNumber = LAST_INSERT_ID();

    INSERT INTO HelpdeskCall (ProblemNumber, CallerEmployeeID, OperatorUserID,
                              CallType, CallReason, UrgencyLevel)
    VALUES (p_ProblemNumber, p_ReporterID, p_OperatorUserID,
            'New', p_CallReason, COALESCE(p_UrgencyLevel, 'Normal'));
  COMMIT;

  SELECT p_ProblemNumber AS ProblemNumber,
         CONCAT('P-', LPAD(p_ProblemNumber, 6, '0')) AS ProblemRef;
END$$

-- Records a follow-up call against an existing problem number (UR-06).
CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_LogFollowUpCall(
  IN p_ProblemNumber  INT,
  IN p_CallerID       CHAR(5),
  IN p_OperatorUserID INT,
  IN p_CallReason     VARCHAR(255),
  IN p_UrgencyLevel   VARCHAR(10),
  IN p_Notes          TEXT)
BEGIN
  DECLARE v_msg VARCHAR(128);
  IF NOT EXISTS (SELECT 1 FROM Problem WHERE ProblemNumber = p_ProblemNumber) THEN
    SET v_msg = CONCAT('Problem number P-', LPAD(p_ProblemNumber, 6, '0'), ' does not exist.');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
  END IF;

  INSERT INTO HelpdeskCall (ProblemNumber, CallerEmployeeID, OperatorUserID,
                            CallType, CallReason, UrgencyLevel, Notes)
  VALUES (p_ProblemNumber, p_CallerID, p_OperatorUserID,
          'Follow-up', p_CallReason, COALESCE(p_UrgencyLevel, 'Normal'), p_Notes);
END$$

-- Lists only skilled technicians, least busy first (UR-08, FR-08).
CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_GetSkilledTechnicians(IN p_ProblemTypeID INT)
BEGIN
  SELECT TechnicianID, TechnicianName, Specialisms, OpenJobs,
         CASE WHEN IsAvailable THEN 'Yes' ELSE 'No (on leave)' END AS Available
  FROM vw_TechnicianWorkload
  WHERE ProblemTypeID = p_ProblemTypeID
  ORDER BY IsAvailable DESC, OpenJobs ASC, TechnicianName;
END$$

-- Assigns a technician; the trigger rejects one without the specialism (FR-09).
CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_AssignTechnician(IN p_ProblemNumber INT, IN p_TechnicianID INT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM Problem
                  WHERE ProblemNumber = p_ProblemNumber AND Status = 'Open') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Problem not found or already closed.';
  END IF;
  UPDATE Problem SET TechnicianID = p_TechnicianID
  WHERE ProblemNumber = p_ProblemNumber;
END$$

-- Closes a problem (UR-09, FR-10). A technician may only close own jobs.
CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_ResolveProblem(
  IN p_ProblemNumber    INT,
  IN p_UserID           INT,
  IN p_DateTimeResolved DATETIME,
  IN p_ResolutionNotes  TEXT,
  IN p_TimeTakenHours   DECIMAL(5,2))
BEGIN
  DECLARE v_role   VARCHAR(12);
  DECLARE v_logged DATETIME;
  DECLARE v_status VARCHAR(6);
  DECLARE v_tech   INT;

  SELECT Role INTO v_role FROM AppUser WHERE UserID = p_UserID AND IsActive;
  IF v_role IS NULL OR v_role NOT IN ('Operator','Technician') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only operators and technicians can resolve problems.';
  END IF;

  SELECT DateTimeLogged, Status, TechnicianID
    INTO v_logged, v_status, v_tech
    FROM Problem WHERE ProblemNumber = p_ProblemNumber;
  IF v_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Problem number does not exist.';
  END IF;
  IF v_status = 'Closed' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This problem is already closed.';
  END IF;
  IF v_role = 'Technician' AND NOT EXISTS (
       SELECT 1 FROM Technician t JOIN AppUser u ON u.EmployeeID = t.EmployeeID
        WHERE u.UserID = p_UserID AND t.TechnicianID = v_tech) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Technicians can only resolve their own jobs.';
  END IF;
  IF p_ResolutionNotes IS NULL OR CHAR_LENGTH(TRIM(p_ResolutionNotes)) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Please describe how the problem was resolved.';
  END IF;
  IF p_TimeTakenHours IS NULL OR p_TimeTakenHours < 0.25 OR p_TimeTakenHours > 500 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Time taken must be between 0.25 and 500 hours.';
  END IF;
  IF p_DateTimeResolved IS NULL OR p_DateTimeResolved <= v_logged THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Resolved time must be after the logged time.';
  END IF;

  UPDATE Problem
     SET Status           = 'Closed',
         DateTimeResolved = p_DateTimeResolved,
         ResolutionNotes  = p_ResolutionNotes,
         TimeTakenHours   = p_TimeTakenHours,
         ResolvedByUserID = p_UserID
   WHERE ProblemNumber = p_ProblemNumber;
END$$


-- =====================================================================
-- STORED PROCEDURES - maintenance (Section 12)
-- Backup itself is run by mysqldump from the application; these record
-- the action and enforce who may restore / compact (UR-15, UR-16).
-- =====================================================================

CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_LogMaintenance(
  IN p_UserID     INT,
  IN p_ActionType VARCHAR(8),
  IN p_BackupFile VARCHAR(255),
  IN p_Outcome    VARCHAR(7))
BEGIN
  DECLARE v_role VARCHAR(12);
  -- p_UserID NULL = the scheduled nightly backup, which has no user
  IF NOT (p_UserID IS NULL AND p_ActionType = 'Backup') THEN
    SELECT Role INTO v_role FROM AppUser WHERE UserID = p_UserID AND IsActive;
    IF v_role IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown or locked user.';
    END IF;
    IF p_ActionType IN ('Restore','Compact') AND v_role <> 'Operator' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only helpdesk operators can restore or compact the database.';
    END IF;
    IF p_ActionType = 'Check' AND v_role <> 'Admin' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only the system administrator can run the integrity check.';
    END IF;
  END IF;
  INSERT INTO MaintenanceLog (UserID, ActionType, BackupFile, Outcome)
  VALUES (p_UserID, p_ActionType, p_BackupFile, p_Outcome);
END$$


-- Compacts every table with OPTIMIZE TABLE (Operators only) and logs it.
CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_CompactDatabase(IN p_UserID INT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM AppUser
                  WHERE UserID = p_UserID AND Role = 'Operator' AND IsActive) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only helpdesk operators can restore or compact the database.';
  END IF;
  OPTIMIZE TABLE Department, Office, Employee, AppUser, Technician, ProblemType,
                 TechnicianSpecialism, Manufacturer, EquipmentType, Hardware,
                 Software, SoftwareLicence, InstalledSoftware, Problem,
                 HelpdeskCall, MaintenanceLog;
  CALL sp_LogMaintenance(p_UserID, 'Compact', NULL, 'Success');
END$$


-- =====================================================================
-- STORED PROCEDURES - management reports R1-R6 (Section 10)
-- A problem belongs to the month in which it was logged.
-- p_Year / p_Month select the month, e.g. CALL sp_Report_R1(2026, 10);
-- =====================================================================

-- R1 - Monthly calls summary: totals, then closed cases with hours.
CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_Report_R1(IN p_Year INT, IN p_Month INT)
BEGIN
  DECLARE v_from DATETIME;
  DECLARE v_to   DATETIME;
  SET v_from = MAKEDATE(p_Year, 1) + INTERVAL (p_Month - 1) MONTH;
  SET v_to   = v_from + INTERVAL 1 MONTH;

  SELECT DATE_FORMAT(v_from, '%M %Y') AS ReportMonth,
         (SELECT COUNT(*) FROM HelpdeskCall
           WHERE CallDateTime >= v_from AND CallDateTime < v_to) AS TotalCallsLogged,
         (SELECT COUNT(*) FROM HelpdeskCall
           WHERE CallType = 'New'
             AND CallDateTime >= v_from AND CallDateTime < v_to) AS NewCalls,
         (SELECT COUNT(*) FROM HelpdeskCall
           WHERE CallType = 'Follow-up'
             AND CallDateTime >= v_from AND CallDateTime < v_to) AS FollowUpCalls,
         COUNT(p.ProblemNumber)                                AS ProblemsLogged,
         COALESCE(SUM(p.Status = 'Open'), 0)                   AS OpenCases,
         COALESCE(SUM(p.Status = 'Closed'), 0)                 AS ClosedCases,
         COALESCE(SUM(CASE WHEN p.Status = 'Closed'
                           THEN p.TimeTakenHours END), 0)      AS HoursOnClosedCases
  FROM Problem p
  WHERE p.DateTimeLogged >= v_from AND p.DateTimeLogged < v_to;

  SELECT d.ProblemRef      AS `Problem No.`,
         DATE(d.DateTimeLogged)   AS Logged,
         DATE(d.DateTimeResolved) AS Resolved,
         d.ProblemType     AS `Problem type`,
         d.TechnicianName  AS Technician,
         d.TimeTakenHours  AS Hours
  FROM vw_ProblemDetail d
  WHERE d.Status = 'Closed'
    AND d.DateTimeLogged >= v_from AND d.DateTimeLogged < v_to
  ORDER BY d.ProblemNumber;
END$$

-- R2 - Technician job sheet summary for a month.
CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_Report_R2(IN p_Year INT, IN p_Month INT)
BEGIN
  DECLARE v_from DATETIME;
  DECLARE v_to   DATETIME;
  SET v_from = MAKEDATE(p_Year, 1) + INTERVAL (p_Month - 1) MONTH;
  SET v_to   = v_from + INTERVAL 1 MONTH;

  SELECT CONCAT(e.FirstName, ' ', e.LastName)              AS Technician,
         COUNT(p.ProblemNumber)                             AS JobsWorked,
         COALESCE(SUM(p.Status = 'Open'), 0)                AS OpenJobs,
         COALESCE(SUM(p.Status = 'Closed'), 0)              AS ClosedJobs,
         COALESCE(SUM(p.TimeTakenHours), 0)                 AS TotalHours,
         ROUND(AVG(p.TimeTakenHours), 2)                    AS AvgHoursPerClosedJob
  FROM Technician t
  JOIN Employee e     ON e.EmployeeID = t.EmployeeID
  LEFT JOIN Problem p ON p.TechnicianID = t.TechnicianID
                     AND p.DateTimeLogged >= v_from AND p.DateTimeLogged < v_to
  GROUP BY t.TechnicianID, e.FirstName, e.LastName
  ORDER BY JobsWorked DESC, Technician;

  SELECT 'All technicians'                      AS Technician,
         COUNT(*)                               AS JobsWorked,
         COALESCE(SUM(Status = 'Open'), 0)      AS OpenJobs,
         COALESCE(SUM(Status = 'Closed'), 0)    AS ClosedJobs,
         COALESCE(SUM(TimeTakenHours), 0)       AS TotalHours,
         ROUND(AVG(TimeTakenHours), 2)          AS AvgHoursPerClosedJob
  FROM Problem
  WHERE TechnicianID IS NOT NULL
    AND DateTimeLogged >= v_from AND DateTimeLogged < v_to;
END$$

-- R3 - Jobs by office. Pass NULLs for an all-time report.
CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_Report_R3(IN p_Year INT, IN p_Month INT)
BEGIN
  DECLARE v_from DATETIME DEFAULT '1000-01-01';
  DECLARE v_to   DATETIME DEFAULT '9999-12-31';
  IF p_Year IS NOT NULL AND p_Month IS NOT NULL THEN
    SET v_from = MAKEDATE(p_Year, 1) + INTERVAL (p_Month - 1) MONTH;
    SET v_to   = v_from + INTERVAL 1 MONTH;
  END IF;

  SELECT CONCAT(o.OfficeName, ' - ', o.Specialisation)            AS Office,
         COALESCE(SUM(pt.FaultCategory = 'Hardware'), 0)          AS HardwareFaults,
         COALESCE(SUM(pt.FaultCategory = 'Software'), 0)          AS SoftwareFaults,
         COUNT(p.ProblemNumber)                                    AS TotalJobs,
         COALESCE(SUM(p.TimeTakenHours), 0)                        AS HoursSpent,
         COUNT(DISTINCT p.TechnicianID)                            AS DifferentTechnicians
  FROM Office o
  LEFT JOIN Employee e     ON e.OfficeID = o.OfficeID
  LEFT JOIN Problem p      ON p.ReportedByEmployeeID = e.EmployeeID
                          AND p.DateTimeLogged >= v_from AND p.DateTimeLogged < v_to
  LEFT JOIN ProblemType pt ON pt.ProblemTypeID = p.ProblemTypeID
  GROUP BY o.OfficeID, o.OfficeName, o.Specialisation
  ORDER BY TotalJobs DESC, Office;
END$$

-- R4 - Jobs per equipment register item (hardware, then software).
-- Action: 3+ jobs under warranty -> warranty claim; 3+ out of warranty -> replace.
CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_Report_R4()
BEGIN
  SELECT hs.SerialNumber                                    AS `Serial no.`,
         CONCAT(hs.ManufacturerName, ' ', hs.Make, ' ', hs.Model) AS Item,
         hs.EquipmentType                                   AS Type,
         hs.OfficeName                                      AS Office,
         COUNT(p.ProblemNumber)                             AS TotalJobs,
         COALESCE(SUM(p.DateTimeLogged >= CURRENT_DATE - INTERVAL 90 DAY), 0) AS Last90Days,
         hs.WarrantyStatus                                  AS Warranty,
         CASE WHEN COUNT(p.ProblemNumber) >= 3 AND hs.WarrantyStatus = 'Under warranty'
                   THEN 'Warranty claim'
              WHEN COUNT(p.ProblemNumber) >= 3
                   THEN 'Consider replacing'
              ELSE 'OK' END                                 AS Action
  FROM vw_HardwareStatus hs
  LEFT JOIN Problem p ON p.HardwareID = hs.HardwareID
  GROUP BY hs.HardwareID, hs.SerialNumber, hs.ManufacturerName, hs.Make, hs.Model,
           hs.EquipmentType, hs.OfficeName, hs.WarrantyStatus
  ORDER BY TotalJobs DESC, hs.SerialNumber;

  SELECT CONCAT(s.SoftwareName, ' ', s.Version)             AS Software,
         s.SoftwareCategory                                 AS Category,
         (SELECT GROUP_CONCAT(l.LicenceNumber ORDER BY l.LicenceNumber SEPARATOR ', ')
            FROM SoftwareLicence l WHERE l.SoftwareID = s.SoftwareID) AS Licences,
         COUNT(p.ProblemNumber)                             AS TotalJobs,
         COALESCE(SUM(p.DateTimeLogged >= CURRENT_DATE - INTERVAL 90 DAY), 0) AS Last90Days,
         CASE WHEN EXISTS (SELECT 1 FROM vw_LicenceStatus v
                            WHERE v.SoftwareID = s.SoftwareID AND v.LicenceStatus = 'Valid')
              THEN 'Valid' ELSE 'No valid licence' END      AS Licence,
         CASE WHEN NOT EXISTS (SELECT 1 FROM vw_LicenceStatus v
                                WHERE v.SoftwareID = s.SoftwareID AND v.LicenceStatus = 'Valid')
                   THEN 'Renew licence'
              WHEN COUNT(p.ProblemNumber) >= 3 THEN 'Review with vendor'
              ELSE 'OK' END                                 AS Action
  FROM Software s
  LEFT JOIN Problem p ON p.SoftwareID = s.SoftwareID OR p.OSSoftwareID = s.SoftwareID
  GROUP BY s.SoftwareID, s.SoftwareName, s.Version, s.SoftwareCategory
  ORDER BY TotalJobs DESC, Software;
END$$

-- R5 - Detail for one technician for one month, with totals.
CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_Report_R5(IN p_TechnicianID INT, IN p_Year INT, IN p_Month INT)
BEGIN
  DECLARE v_from DATETIME;
  DECLARE v_to   DATETIME;
  SET v_from = MAKEDATE(p_Year, 1) + INTERVAL (p_Month - 1) MONTH;
  SET v_to   = v_from + INTERVAL 1 MONTH;

  SELECT d.ProblemRef              AS `Problem No.`,
         DATE(d.DateTimeLogged)    AS Logged,
         d.OfficeName              AS Office,
         CONCAT_WS(' - ', d.SerialNumber, d.EquipmentName) AS Equipment,
         d.ProblemType             AS `Problem type`,
         d.Status,
         d.TimeTakenHours          AS Hours
  FROM vw_ProblemDetail d
  WHERE d.TechnicianID = p_TechnicianID
    AND d.DateTimeLogged >= v_from AND d.DateTimeLogged < v_to
  ORDER BY d.ProblemNumber;

  SELECT CONCAT(e.FirstName, ' ', e.LastName)   AS Technician,
         DATE_FORMAT(v_from, '%M %Y')            AS ReportMonth,
         COALESCE(SUM(p.Status = 'Open'), 0)     AS OpenJobs,
         COALESCE(SUM(p.Status = 'Closed'), 0)   AS ClosedJobs,
         COALESCE(SUM(p.TimeTakenHours), 0)      AS TotalHours,
         ROUND(AVG(p.TimeTakenHours), 2)         AS AvgHoursPerClosedJob
  FROM Technician t
  JOIN Employee e     ON e.EmployeeID = t.EmployeeID
  LEFT JOIN Problem p ON p.TechnicianID = t.TechnicianID
                     AND p.DateTimeLogged >= v_from AND p.DateTimeLogged < v_to
  WHERE t.TechnicianID = p_TechnicianID
  GROUP BY e.FirstName, e.LastName;
END$$

-- R6 - Detail for one office for one month, totals, and jobs per register item.
CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_Report_R6(IN p_OfficeID INT, IN p_Year INT, IN p_Month INT)
BEGIN
  DECLARE v_from DATETIME;
  DECLARE v_to   DATETIME;
  SET v_from = MAKEDATE(p_Year, 1) + INTERVAL (p_Month - 1) MONTH;
  SET v_to   = v_from + INTERVAL 1 MONTH;

  SELECT d.ProblemRef           AS `Problem No.`,
         DATE(d.DateTimeLogged) AS Logged,
         d.ReporterName         AS `Reported by`,
         COALESCE(d.EquipmentName, d.Application) AS Equipment,
         d.ProblemType          AS Type,
         d.TechnicianName       AS Technician,
         d.Status,
         d.TimeTakenHours       AS Hours
  FROM vw_ProblemDetail d
  WHERE d.OfficeID = p_OfficeID
    AND d.DateTimeLogged >= v_from AND d.DateTimeLogged < v_to
  ORDER BY d.ProblemNumber;

  SELECT CONCAT(o.OfficeName, ' - ', o.Specialisation) AS Office,
         DATE_FORMAT(v_from, '%M %Y')                 AS ReportMonth,
         COUNT(d.ProblemNumber)                        AS TotalJobs,
         COALESCE(SUM(d.Status = 'Open'), 0)           AS OpenJobs,
         COALESCE(SUM(d.Status = 'Closed'), 0)         AS ClosedJobs,
         COALESCE(SUM(d.TimeTakenHours), 0)            AS TotalHours
  FROM Office o
  LEFT JOIN vw_ProblemDetail d ON d.OfficeID = o.OfficeID
                              AND d.DateTimeLogged >= v_from AND d.DateTimeLogged < v_to
  WHERE o.OfficeID = p_OfficeID
  GROUP BY o.OfficeName, o.Specialisation;

  SELECT x.RegisterItem AS `Register item`,
         x.RegisterNo   AS `Serial / licence no.`,
         COUNT(*)       AS JobsThisMonth,
         CASE WHEN COUNT(*) >= 3 THEN 'Regularly failing - review' ELSE '-' END AS Flag
  FROM (
    SELECT d.EquipmentName AS RegisterItem, d.SerialNumber AS RegisterNo
      FROM vw_ProblemDetail d
     WHERE d.OfficeID = p_OfficeID AND d.HardwareID IS NOT NULL
       AND d.DateTimeLogged >= v_from AND d.DateTimeLogged < v_to
    UNION ALL
    SELECT d.Application,
           (SELECT GROUP_CONCAT(l.LicenceNumber ORDER BY l.LicenceNumber SEPARATOR ', ')
              FROM SoftwareLicence l WHERE l.SoftwareID = d.SoftwareID)
      FROM vw_ProblemDetail d
     WHERE d.OfficeID = p_OfficeID AND d.SoftwareID IS NOT NULL
       AND d.DateTimeLogged >= v_from AND d.DateTimeLogged < v_to
  ) x
  GROUP BY x.RegisterItem, x.RegisterNo
  ORDER BY JobsThisMonth DESC, x.RegisterItem;
END$$

DELIMITER ;

-- Quick check
SHOW FULL TABLES WHERE Table_type = 'VIEW';
SHOW TRIGGERS;
SHOW PROCEDURE STATUS WHERE Db = 'mlhelpdesk';
