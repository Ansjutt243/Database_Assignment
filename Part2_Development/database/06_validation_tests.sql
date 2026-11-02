-- =====================================================================
-- ML IT Helpdesk System  |  Script 06 of 06: automated database tests
-- Tests the validation rules of Design Document Section 8.3 and the
-- business rules BR-01..BR-13 at the DATABASE layer.
-- Every test runs inside a transaction that is rolled back, so the pilot
-- data is unchanged afterwards. Result: one row per test, PASS or FAIL.
-- Usage:  CALL sp_RunValidationTests();
-- =====================================================================
USE mlhelpdesk;

DROP PROCEDURE IF EXISTS sp_TestRecord;
DROP PROCEDURE IF EXISTS sp_RunValidationTests;

DELIMITER $$

CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_TestRecord(
  IN p_TestID VARCHAR(6), IN p_Rule VARCHAR(20), IN p_Description VARCHAR(120),
  IN p_TestData VARCHAR(80), IN p_Expected VARCHAR(8), IN p_Error INT, IN p_Message VARCHAR(255))
BEGIN
  DECLARE v_actual VARCHAR(8);
  SET v_actual = IF(p_Error = 1, 'Rejected', 'Accepted');
  INSERT INTO TestResult VALUES
    (p_TestID, p_Rule, p_Description, p_TestData, p_Expected, v_actual,
     COALESCE(p_Message, '-'), IF(v_actual = p_Expected, 'PASS', 'FAIL'));
END$$

CREATE DEFINER = 'ml_owner'@'localhost' PROCEDURE sp_RunValidationTests()
BEGIN
  DECLARE v_err   INT DEFAULT 0;
  DECLARE v_msg   VARCHAR(255);
  DECLARE v_open  INT;          -- an open problem assigned to a technician
  DECLARE v_new   INT;          -- problem created during the tests
  DECLARE v_pno   INT;
  DECLARE v_before INT;
  DECLARE v_after  INT;

  DROP TEMPORARY TABLE IF EXISTS TestResult;
  -- MEMORY engine is not transactional, so results survive the ROLLBACK
  CREATE TEMPORARY TABLE TestResult (
    TestID VARCHAR(6), RuleRef VARCHAR(20), Description VARCHAR(120),
    TestData VARCHAR(80), Expected VARCHAR(8), Actual VARCHAR(8),
    DatabaseMessage VARCHAR(255), Result VARCHAR(4)
  ) ENGINE=MEMORY;

  -- -------------------------------------------------------------------
  -- Phase A: atomicity of sp_LogNewProblem (it manages its own transaction)
  -- -------------------------------------------------------------------
  SELECT COUNT(*) INTO v_before FROM Problem;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    -- blank call reason makes the 2nd insert fail -> the problem must be rolled back
    CALL sp_LogNewProblem('E0045', 1, 3, NULL, NULL, NULL,
         'Printer jams on every page today', 'High', '', 'Normal', v_pno);
  END;
  SELECT COUNT(*) INTO v_after FROM Problem;
  CALL sp_TestRecord('TT-01', 'NFR-04', 'New problem + call saved as one transaction; failed call rolls back problem',
       CONCAT('rows before=', v_before, ' after=', v_after), 'Rejected',
       IF(v_err = 1 AND v_before = v_after, 1, 0), v_msg);

  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL, v_pno = NULL;
    CALL sp_LogNewProblem('E0045', 1, 3, NULL, NULL, NULL,
         'Printer jams on every page today', 'High', 'Printer jammed', 'Urgent', v_pno);
    CALL sp_TestRecord('TT-02', 'UR-05, FR-03', 'Valid new call gets an automatic problem number',
         CONCAT('P-', LPAD(v_pno, 6, '0')), 'Accepted', v_err, v_msg);
    -- clean up the row created by this test
    DELETE FROM HelpdeskCall WHERE ProblemNumber = v_pno;
    DELETE FROM Problem      WHERE ProblemNumber = v_pno;
  END;

  -- -------------------------------------------------------------------
  -- Phase B: everything below is rolled back at the end
  -- -------------------------------------------------------------------
  START TRANSACTION;

  SELECT MIN(ProblemNumber) INTO v_open FROM Problem
   WHERE Status = 'Open' AND TechnicianID IS NOT NULL AND TechnicianID <> 3;

  -- VT-01 ex-employee as caller (BR-04)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Problem (ReportedByEmployeeID, ProblemTypeID, Description)
    VALUES ('E0012', 1, 'PC will not start this morning');
    CALL sp_TestRecord('VT-01', 'BR-04', 'Caller must be a current employee', 'Zara Noor (left 30/06/2025)', 'Rejected', v_err, v_msg);
  END;

  -- VT-02 valid problem by current employee
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    -- logged 3 hours ago: NOW() is fixed for the whole CALL, so the later
    -- resolve tests need a logged time that is earlier than NOW()
    INSERT INTO Problem (ReportedByEmployeeID, ProblemTypeID, Description, Priority, DateTimeLogged)
    VALUES ('E0045', 3, 'Screen flickers after update', 'High', NOW() - INTERVAL 3 HOUR);
    SET v_new = LAST_INSERT_ID();
    CALL sp_TestRecord('VT-02', 'UR-01', 'Valid problem by current employee', 'Bilal Ahmed, Printer, High', 'Accepted', v_err, v_msg);
  END;

  -- VT-03 blank call reason (UR-06)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO HelpdeskCall (ProblemNumber, CallerEmployeeID, OperatorUserID, CallType, CallReason)
    VALUES (v_new, 'E0045', 1, 'New', '  ');
    CALL sp_TestRecord('VT-03', 'UR-06', 'Reason for call is always required', '(blank)', 'Rejected', v_err, v_msg);
  END;

  -- VT-04 valid follow-up call with distress
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    CALL sp_LogFollowUpCall(v_new, 'E0045', 1, 'Chasing - deadline today', 'Distressed', NULL);
    CALL sp_TestRecord('VT-04', 'UR-06', 'Follow-up call recorded against existing problem', 'Chasing - deadline today', 'Accepted', v_err, v_msg);
  END;

  -- VT-05 follow-up for a problem number that does not exist
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    CALL sp_LogFollowUpCall(999999, 'E0045', 1, 'Any news on my problem', 'Normal', NULL);
    CALL sp_TestRecord('VT-05', 'Referential', 'Follow-up must quote an existing problem number', 'P-999999', 'Rejected', v_err, v_msg);
  END;

  -- VT-06 call logged by a non-operator account (BR-10)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO HelpdeskCall (ProblemNumber, CallerEmployeeID, OperatorUserID, CallType, CallReason)
    VALUES (v_new, 'E0045', 12, 'Follow-up', 'Manager tries to log a call');
    CALL sp_TestRecord('VT-06', 'UR-14, BR-10', 'Only helpdesk operators can log calls', 'i.shah (Manager)', 'Rejected', v_err, v_msg);
  END;

  -- VT-07 description too short
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Problem (ReportedByEmployeeID, ProblemTypeID, Description) VALUES ('E0045', 3, 'Broken');
    CALL sp_TestRecord('VT-07', 'Presence/length', 'Description at least 10 characters', 'Broken', 'Rejected', v_err, v_msg);
  END;

  -- VT-08 invalid priority
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Problem (ReportedByEmployeeID, ProblemTypeID, Description, Priority)
    VALUES ('E0045', 3, 'Printer jams on every page', 'Very high');
    CALL sp_TestRecord('VT-08', 'List', 'Priority must be Low/Medium/High/Critical', 'Very high', 'Rejected', v_err, v_msg);
  END;

  -- VT-09 problem type that does not exist
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Problem (ReportedByEmployeeID, ProblemTypeID, Description) VALUES ('E0045', 99, 'Unknown device problem');
    CALL sp_TestRecord('VT-09', 'Lookup', 'Problem type must be chosen from the list', 'ProblemTypeID 99', 'Rejected', v_err, v_msg);
  END;

  -- VT-10 technician without the specialism (FR-09) - Hamza Ali has no Printer skill
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    CALL sp_AssignTechnician(v_new, 1);
    CALL sp_TestRecord('VT-10', 'FR-09, BR-11', 'Technician must have the specialism', 'Hamza Ali for Printer', 'Rejected', v_err, v_msg);
  END;

  -- VT-11 skilled technician accepted, DateTimeAssigned stamped
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    CALL sp_AssignTechnician(v_new, 3);
    IF (SELECT DateTimeAssigned FROM Problem WHERE ProblemNumber = v_new) IS NULL THEN SET v_err = 1, v_msg = 'DateTimeAssigned not set'; END IF;
    CALL sp_TestRecord('VT-11', 'UR-08', 'Skilled technician assigned; time stamped by trigger', 'Usman Tariq for Printer', 'Accepted', v_err, v_msg);
  END;

  -- VT-12 resolved time before logged time
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    CALL sp_ResolveProblem(v_new, 1, NOW() - INTERVAL 2 DAY, 'Replaced toner', 2.0);
    CALL sp_TestRecord('VT-12', 'Cross-field', 'Resolved time must be after logged time', 'resolved 2 days before logged', 'Rejected', v_err, v_msg);
  END;

  -- VT-13 resolved time in the future (trigger)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    UPDATE Problem SET Status = 'Closed', DateTimeResolved = NOW() + INTERVAL 1 DAY,
           ResolutionNotes = 'Replaced toner', TimeTakenHours = 1 WHERE ProblemNumber = v_new;
    CALL sp_TestRecord('VT-13', 'Range', 'Resolved time cannot be in the future', 'tomorrow', 'Rejected', v_err, v_msg);
  END;

  -- VT-14 time taken out of range
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    UPDATE Problem SET Status = 'Closed', DateTimeResolved = NOW(),
           ResolutionNotes = 'Replaced toner', TimeTakenHours = 0 WHERE ProblemNumber = v_new;
    CALL sp_TestRecord('VT-14', 'Range', 'Time taken between 0.25 and 500 hours', '0', 'Rejected', v_err, v_msg);
  END;

  -- VT-15 closing without resolution notes (BR-12)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    UPDATE Problem SET Status = 'Closed', DateTimeResolved = NOW(),
           ResolutionNotes = NULL, TimeTakenHours = 1 WHERE ProblemNumber = v_new;
    CALL sp_TestRecord('VT-15', 'BR-12', 'Closed problem needs resolution notes', '(blank)', 'Rejected', v_err, v_msg);
  END;

  -- VT-16 technician resolves a job that is not theirs
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    CALL sp_ResolveProblem(v_open, 8, NOW(), 'Fixed it', 1.0);
    CALL sp_TestRecord('VT-16', 'Access matrix', 'Technicians resolve own jobs only', CONCAT('u.tariq on P-', LPAD(v_open, 6, '0')), 'Rejected', v_err, v_msg);
  END;

  -- VT-17 valid resolution by the assigned technician
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    CALL sp_ResolveProblem(v_new, 8, NOW(), 'Replaced toner and cleaned rollers', 0.75);
    IF (SELECT Status FROM Problem WHERE ProblemNumber = v_new) <> 'Closed' THEN SET v_err = 1, v_msg = 'Status not Closed'; END IF;
    CALL sp_TestRecord('VT-17', 'UR-09, FR-10', 'Valid resolution closes the problem', 'now, 0.75 h, notes', 'Accepted', v_err, v_msg);
  END;

  -- VT-18 employee ID format
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Employee VALUES ('E45A', 'Test', 'User', 'Clerk', '042-1234567', 'test.user@ml.co.uk', '2024-01-01', NULL, 1, 1);
    CALL sp_TestRecord('VT-18', 'Format', 'Employee ID = E + 4 digits', 'E45A', 'Rejected', v_err, v_msg);
  END;

  -- VT-19 email format
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Employee VALUES ('E0901', 'Test', 'User', 'Clerk', '042-1234567', 'b.ahmed@ml', '2024-01-01', NULL, 1, 1);
    CALL sp_TestRecord('VT-19', 'Format', 'Email must contain @ and a dot after it', 'b.ahmed@ml', 'Rejected', v_err, v_msg);
  END;

  -- VT-20 duplicate email
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Employee VALUES ('E0902', 'Test', 'User', 'Clerk', '042-1234567', 'bilal.ahmed@ml.co.uk', '2024-01-01', NULL, 1, 1);
    CALL sp_TestRecord('VT-20', 'Uniqueness', 'Email must not already exist', 'bilal.ahmed@ml.co.uk', 'Rejected', v_err, v_msg);
  END;

  -- VT-21 phone format
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Employee VALUES ('E0903', 'Test', 'User', 'Clerk', '042-ABC', 'test.user3@ml.co.uk', '2024-01-01', NULL, 1, 1);
    CALL sp_TestRecord('VT-21', 'Format', 'Phone: digits, spaces, + and - only', '042-ABC', 'Rejected', v_err, v_msg);
  END;

  -- VT-22 end date before start date (BR-03)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Employee VALUES ('E0904', 'Test', 'User', 'Clerk', '042-1234567', 'test.user4@ml.co.uk', '2020-01-01', '2019-01-01', 1, 1);
    CALL sp_TestRecord('VT-22', 'BR-03', 'End date cannot be before start date', 'start 2020, end 2019', 'Rejected', v_err, v_msg);
  END;

  -- VT-23 start date in the future (trigger)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Employee VALUES ('E0905', 'Test', 'User', 'Clerk', '042-1234567', 'test.user5@ml.co.uk', CURRENT_DATE + INTERVAL 1 MONTH, NULL, 1, 1);
    CALL sp_TestRecord('VT-23', 'Range', 'Start date cannot be in the future', 'next month', 'Rejected', v_err, v_msg);
  END;

  -- VT-24 valid new employee
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Employee VALUES ('E0906', 'Test', 'O''Brien', 'Clerk', '+92 42 1234567', 'test.obrien@ml.co.uk', '2024-01-01', NULL, 1, 1);
    CALL sp_TestRecord('VT-24', 'UR-10', 'Valid employee (apostrophe in name, + in phone)', 'E0906 Test O''Brien', 'Accepted', v_err, v_msg);
  END;

  -- VT-25 warranty before purchase
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Hardware (SerialNumber, EquipmentTypeID, ManufacturerID, Make, Model, PurchaseDate, WarrantyExpiry, OfficeID)
    VALUES ('TEST-0001', 1, 1, 'ProDesk', '400 G9', '2025-03-15', '2024-01-01', 1);
    CALL sp_TestRecord('VT-25', 'Cross-field', 'Warranty cannot end before purchase', 'purchase 2025, warranty 2024', 'Rejected', v_err, v_msg);
  END;

  -- VT-26 duplicate serial number
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Hardware (SerialNumber, EquipmentTypeID, ManufacturerID, Make, Model, PurchaseDate, WarrantyExpiry, OfficeID)
    VALUES ('HP-5CG1234XYZ', 2, 1, 'EliteBook', '840 G9', '2025-03-15', '2028-03-14', 1);
    CALL sp_TestRecord('VT-26', 'Uniqueness', 'Serial number must be unique', 'HP-5CG1234XYZ', 'Rejected', v_err, v_msg);
  END;

  -- VT-27 serial number with illegal characters
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Hardware (SerialNumber, EquipmentTypeID, ManufacturerID, Make, Model, PurchaseDate, WarrantyExpiry, OfficeID)
    VALUES ('HP 123/45', 2, 1, 'EliteBook', '840 G9', '2025-03-15', '2028-03-14', 1);
    CALL sp_TestRecord('VT-27', 'Format', 'Serial: letters, digits and - only', 'HP 123/45', 'Rejected', v_err, v_msg);
  END;

  -- VT-28 licence seats out of range
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO SoftwareLicence (LicenceNumber, SoftwareID, LicenceType, Seats, StartDate)
    VALUES ('LIC-TEST-0001', 3, 'Volume', 0, '2026-01-01');
    CALL sp_TestRecord('VT-28', 'Range', 'Seats must be 1 to 10,000', '0', 'Rejected', v_err, v_msg);
  END;

  -- VT-29 office specialisation not in the list (BR-01)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO Office (OfficeName, AddressLine, City, Postcode, ContactPhone, Specialisation)
    VALUES ('Test Office', '1 Test Road', 'Lahore', '54000', '042-1111111', 'Retail');
    CALL sp_TestRecord('VT-29', 'BR-01', 'Specialisation from the fixed list of seven', 'Retail', 'Rejected', v_err, v_msg);
  END;

  -- VT-30 username too short
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO AppUser (Username, PasswordHash, Role, EmployeeID)
    SELECT 'ak', PasswordHash, 'Operator', 'E0005' FROM AppUser WHERE UserID = 1;
    CALL sp_TestRecord('VT-30', 'Length', 'Username 4-30 characters', 'ak', 'Rejected', v_err, v_msg);
  END;

  -- VT-31 plain-text password refused
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO AppUser (Username, PasswordHash, Role, EmployeeID)
    VALUES ('test.user', 'Helpdesk2026', 'Operator', 'E0005');
    CALL sp_TestRecord('VT-31', 'NFR-02', 'Only bcrypt hashes stored, never plain text', 'Helpdesk2026', 'Rejected', v_err, v_msg);
  END;

  -- VT-32 invalid role
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO AppUser (Username, PasswordHash, Role, EmployeeID)
    SELECT 'test.user', PasswordHash, 'Director', 'E0005' FROM AppUser WHERE UserID = 1;
    CALL sp_TestRecord('VT-32', 'BR-13', 'Role must be Operator/Technician/Manager/Admin', 'Director', 'Rejected', v_err, v_msg);
  END;

  -- VT-33 restore by a non-operator (UR-16)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    CALL sp_LogMaintenance(12, 'Restore', 'mlhelpdesk_20261101_1800.sql', 'Success');
    CALL sp_TestRecord('VT-33', 'UR-16', 'Only operators can restore', 'i.shah (Manager)', 'Rejected', v_err, v_msg);
  END;

  -- VT-34 backup by a manager (UR-15)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    CALL sp_LogMaintenance(12, 'Backup', 'mlhelpdesk_test.sql', 'Success');
    CALL sp_TestRecord('VT-34', 'UR-15', 'Backup is available to every user', 'i.shah (Manager)', 'Accepted', v_err, v_msg);
  END;

  -- VT-35 deleting an office that still has employees (referential integrity)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    DELETE FROM Office WHERE OfficeID = 1;
    CALL sp_TestRecord('VT-35', 'FR-12', 'Cannot delete an office that has employees', 'Lahore', 'Rejected', v_err, v_msg);
  END;

  -- VT-36 deleting an employee with call history (history is kept, BR-03)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    DELETE FROM Employee WHERE EmployeeID = 'E0045';
    CALL sp_TestRecord('VT-36', 'FR-16', 'Employees with history are end-dated, not deleted', 'E0045', 'Rejected', v_err, v_msg);
  END;

  -- VT-37 integrity check by a non-admin (Table 41)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    CALL sp_LogMaintenance(1, 'Check', NULL, 'Success');
    CALL sp_TestRecord('VT-37', 'Table 41', 'Only the administrator runs the integrity check', 'a.khan (Operator)', 'Rejected', v_err, v_msg);
  END;

  -- VT-38 scheduled nightly backup is logged without a user (Table 41)
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    CALL sp_LogMaintenance(NULL, 'Backup', 'mlhelpdesk_test_1800.sql', 'Success');
    CALL sp_TestRecord('VT-38', 'Table 41', 'Scheduled backup logged as System', 'UserID NULL, Backup', 'Accepted', v_err, v_msg);
  END;

  -- VT-39 only a backup may be logged without a user
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT; SET v_err = 1; END;
    SET v_err = 0, v_msg = NULL;
    INSERT INTO MaintenanceLog (UserID, ActionType, Outcome) VALUES (NULL, 'Restore', 'Success');
    CALL sp_TestRecord('VT-39', 'UR-16', 'Restore always records who did it', 'UserID NULL, Restore', 'Rejected', v_err, v_msg);
  END;

  ROLLBACK;

  SELECT * FROM TestResult ORDER BY TestID;
  SELECT COUNT(*) AS TestsRun, SUM(Result = 'PASS') AS Passed, SUM(Result = 'FAIL') AS Failed
  FROM TestResult;
  DROP TEMPORARY TABLE TestResult;
END$$

DELIMITER ;

CALL sp_RunValidationTests();
