-- =====================================================================
-- ML IT Helpdesk System  |  Script 05 of 06: queries and reports
-- Evidence of querying across multiple tables (P2, P3) and of the
-- management information produced by reports R1-R6 (M3).
-- Read-only: safe to run at any time after 03_pilot_data.sql.
-- =====================================================================
USE mlhelpdesk;

-- ---------------------------------------------------------------------
-- Q1 (UR-02, FR-04) Caller look-up by partial name: Employee + Department
-- + Office. Ex-employees are flagged so they cannot be used as callers.
-- ---------------------------------------------------------------------
SELECT e.EmployeeID, CONCAT(e.FirstName, ' ', e.LastName) AS Caller,
       e.JobTitle, d.DepartmentName AS Department, o.OfficeName AS Office,
       CASE WHEN e.EndDate IS NULL OR e.EndDate >= CURRENT_DATE
            THEN 'Current' ELSE CONCAT('Left ', DATE_FORMAT(e.EndDate, '%d/%m/%Y')) END AS EmploymentStatus
FROM Employee e
JOIN Department d ON d.DepartmentID = e.DepartmentID
JOIN Office o     ON o.OfficeID     = e.OfficeID
WHERE CONCAT(e.FirstName, ' ', e.LastName) LIKE CONCAT('%', 'a', '%')
  AND e.LastName IN ('Ahmed', 'Noor', 'Raza')
ORDER BY e.LastName, e.FirstName;

-- ---------------------------------------------------------------------
-- Q2 (UR-03, FR-05) Equipment look-up by serial number with warranty status
-- ---------------------------------------------------------------------
SELECT SerialNumber, EquipmentType, ManufacturerName, Make, Model,
       DATE_FORMAT(WarrantyExpiry, '%d/%m/%Y') AS WarrantyExpiry, WarrantyStatus,
       OfficeName, AssignedTo
FROM vw_HardwareStatus
WHERE SerialNumber IN ('HP-5CG1234XYZ', 'EP-L3150-0042');

-- ---------------------------------------------------------------------
-- Q3 (UR-04, FR-06) Software installed on the caller's computer and
-- whether each licence is valid: Hardware + InstalledSoftware +
-- SoftwareLicence + Software
-- ---------------------------------------------------------------------
SELECT h.SerialNumber, CONCAT(s.SoftwareName, ' ', s.Version) AS Software,
       s.SoftwareCategory, l.LicenceNumber, l.LicenceType,
       COALESCE(DATE_FORMAT(l.ExpiryDate, '%d/%m/%Y'), 'Perpetual') AS Expires,
       CASE WHEN l.ExpiryDate IS NULL OR l.ExpiryDate >= CURRENT_DATE
            THEN 'Valid' ELSE 'Expired' END AS LicenceStatus
FROM Hardware h
JOIN InstalledSoftware i ON i.HardwareID = h.HardwareID
JOIN SoftwareLicence l   ON l.LicenceID  = i.LicenceID
JOIN Software s          ON s.SoftwareID = l.SoftwareID
WHERE h.SerialNumber = 'HP-5CG1234XYZ'
ORDER BY s.SoftwareCategory;

-- ---------------------------------------------------------------------
-- Q4 (UR-08, FR-08) Only technicians skilled in "Printer", least busy first
-- ---------------------------------------------------------------------
CALL sp_GetSkilledTechnicians((SELECT ProblemTypeID FROM ProblemType WHERE TypeName = 'Printer'));

-- ---------------------------------------------------------------------
-- Q5 Open problems list (Helpdesk Jobs > Open Problems List)
-- ---------------------------------------------------------------------
SELECT ProblemRef, DATE_FORMAT(DateTimeLogged, '%d/%m/%Y %H:%i') AS Logged,
       Priority, ProblemType, ReporterName, OfficeName,
       COALESCE(TechnicianName, '(not assigned)') AS Technician, CallCount AS Calls
FROM vw_ProblemDetail
WHERE Status = 'Open'
ORDER BY FIELD(Priority, 'Critical', 'High', 'Medium', 'Low'), DateTimeLogged;

-- ---------------------------------------------------------------------
-- Q6 Call history of one problem (Problem + HelpdeskCall + Employee + AppUser)
-- ---------------------------------------------------------------------
SELECT CONCAT('P-', LPAD(c.ProblemNumber, 6, '0')) AS ProblemRef, c.CallID,
       DATE_FORMAT(c.CallDateTime, '%d/%m/%Y %H:%i') AS CallTime, c.CallType,
       CONCAT(ce.FirstName, ' ', ce.LastName) AS Caller,
       CONCAT(oe.FirstName, ' ', oe.LastName) AS Operator,
       c.CallReason, c.UrgencyLevel
FROM HelpdeskCall c
JOIN Employee ce ON ce.EmployeeID = c.CallerEmployeeID
JOIN AppUser u   ON u.UserID      = c.OperatorUserID
JOIN Employee oe ON oe.EmployeeID = u.EmployeeID
WHERE c.ProblemNumber IN (SELECT ProblemNumber FROM HelpdeskCall
                          GROUP BY ProblemNumber HAVING COUNT(*) > 1)
ORDER BY c.ProblemNumber, c.CallDateTime
LIMIT 10;

-- ---------------------------------------------------------------------
-- Q7 Average resolution time per fault category and priority
-- (extra management information - GROUP BY across 2 tables)
-- ---------------------------------------------------------------------
SELECT pt.FaultCategory, p.Priority, COUNT(*) AS ClosedJobs,
       ROUND(AVG(p.TimeTakenHours), 2) AS AvgHoursWorked,
       ROUND(AVG(TIMESTAMPDIFF(MINUTE, p.DateTimeLogged, p.DateTimeResolved)) / 60, 1) AS AvgHoursToResolve
FROM Problem p
JOIN ProblemType pt ON pt.ProblemTypeID = p.ProblemTypeID
WHERE p.Status = 'Closed'
GROUP BY pt.FaultCategory, p.Priority
ORDER BY pt.FaultCategory, FIELD(p.Priority, 'Critical', 'High', 'Medium', 'Low');

-- =====================================================================
-- MANAGEMENT REPORTS R1-R6 (Design Document Section 10) for October 2026
-- =====================================================================
CALL sp_Report_R1(2026, 10);                        -- R1 Monthly calls summary
CALL sp_Report_R2(2026, 10);                        -- R2 Technician job sheet summary
CALL sp_Report_R3(2026, 10);                        -- R3 Jobs by office (month)
CALL sp_Report_R3(NULL, NULL);                      -- R3 Jobs by office (all time)
CALL sp_Report_R4();                                -- R4 Jobs per register item
CALL sp_Report_R5(3, 2026, 10);                     -- R5 Usman Tariq (TechnicianID 3)
CALL sp_Report_R6(1, 2026, 10);                     -- R6 Lahore office (OfficeID 1)
