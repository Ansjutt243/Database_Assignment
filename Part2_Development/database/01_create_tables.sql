-- =====================================================================
-- ML IT Helpdesk System  |  Script 01 of 06: database and tables
-- Unit 4: Database Design & Development (Part 2 - Development)
-- Platform: MySQL Community Server 8.0 (InnoDB, utf8mb4)
-- Implements Section 7.1 (data dictionary) of the Design Document.
-- WARNING: drops and recreates the mlhelpdesk database.
-- =====================================================================

DROP DATABASE IF EXISTS mlhelpdesk;
CREATE DATABASE mlhelpdesk
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;
USE mlhelpdesk;

-- Owner (DEFINER) of all views, triggers and procedures in script 02.
-- Nobody can log in with it (ACCOUNT LOCK). Objects owned by root could
-- only be replaced by another all-powerful account, so the restore
-- account (ml_maint) would need far more rights than it should have.
DROP USER IF EXISTS 'ml_owner'@'localhost';
CREATE USER 'ml_owner'@'localhost' ACCOUNT LOCK;
GRANT ALL PRIVILEGES ON mlhelpdesk.* TO 'ml_owner'@'localhost';

-- ---------------------------------------------------------------------
-- Department  (Table 14)
-- ---------------------------------------------------------------------
CREATE TABLE Department (
  DepartmentID   INT          NOT NULL AUTO_INCREMENT,
  DepartmentName VARCHAR(50)  NOT NULL,
  CONSTRAINT pk_department PRIMARY KEY (DepartmentID),
  CONSTRAINT uq_department_name UNIQUE (DepartmentName)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Office  (Table 13)
-- ---------------------------------------------------------------------
CREATE TABLE Office (
  OfficeID       INT          NOT NULL AUTO_INCREMENT,
  OfficeName     VARCHAR(60)  NOT NULL,
  AddressLine    VARCHAR(100) NOT NULL,
  City           VARCHAR(40)  NOT NULL,
  Postcode       VARCHAR(10)  NOT NULL,
  ContactPhone   VARCHAR(15)  NOT NULL,
  Specialisation VARCHAR(30)  NOT NULL,
  CONSTRAINT pk_office PRIMARY KEY (OfficeID),
  CONSTRAINT uq_office_name UNIQUE (OfficeName),
  CONSTRAINT chk_office_phone CHECK (ContactPhone REGEXP '^[0-9+ -]{7,15}$'),
  CONSTRAINT chk_office_spec  CHECK (Specialisation IN
    ('Land Development','Property Management','Brokerage','Lending',
     'Legal','Interior Design','Construction'))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Employee  (Table 15) - ex-employees are kept with an EndDate
-- ---------------------------------------------------------------------
CREATE TABLE Employee (
  EmployeeID     CHAR(5)      NOT NULL,
  FirstName      VARCHAR(40)  NOT NULL,
  LastName       VARCHAR(40)  NOT NULL,
  JobTitle       VARCHAR(50)  NOT NULL,
  ContactPhone   VARCHAR(15)  NOT NULL,
  Email          VARCHAR(100) NOT NULL,
  StartDate      DATE         NOT NULL,
  EndDate        DATE         NULL,
  OfficeID       INT          NOT NULL,
  DepartmentID   INT          NOT NULL,
  CONSTRAINT pk_employee PRIMARY KEY (EmployeeID),
  CONSTRAINT uq_employee_email UNIQUE (Email),
  CONSTRAINT chk_emp_id     CHECK (EmployeeID REGEXP '^E[0-9]{4}$'),
  CONSTRAINT chk_emp_first  CHECK (FirstName REGEXP '^[A-Za-z][A-Za-z '' -]*$'),
  CONSTRAINT chk_emp_last   CHECK (LastName  REGEXP '^[A-Za-z][A-Za-z '' -]*$'),
  CONSTRAINT chk_emp_phone  CHECK (ContactPhone REGEXP '^[0-9+ -]{7,15}$'),
  CONSTRAINT chk_emp_email  CHECK (Email REGEXP '^[^@ ]+@[^@ ]+\\.[^@ ]+$'),
  CONSTRAINT chk_emp_dates  CHECK (EndDate IS NULL OR EndDate >= StartDate),
  CONSTRAINT fk_emp_office  FOREIGN KEY (OfficeID)     REFERENCES Office(OfficeID),
  CONSTRAINT fk_emp_dept    FOREIGN KEY (DepartmentID) REFERENCES Department(DepartmentID),
  INDEX idx_emp_name (LastName, FirstName)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- AppUser  (Table 16) - log-in accounts; passwords stored as bcrypt only
-- ---------------------------------------------------------------------
CREATE TABLE AppUser (
  UserID         INT          NOT NULL AUTO_INCREMENT,
  Username       VARCHAR(30)  NOT NULL,
  PasswordHash   VARCHAR(255) NOT NULL,
  Role           VARCHAR(12)  NOT NULL,
  IsActive       BOOLEAN      NOT NULL DEFAULT TRUE,
  FailedLogins   TINYINT      NOT NULL DEFAULT 0,
  EmployeeID     CHAR(5)      NOT NULL,
  CONSTRAINT pk_appuser PRIMARY KEY (UserID),
  CONSTRAINT uq_appuser_username UNIQUE (Username),
  CONSTRAINT uq_appuser_employee UNIQUE (EmployeeID),
  CONSTRAINT chk_user_name   CHECK (Username REGEXP '^[A-Za-z0-9._]{4,30}$'),
  CONSTRAINT chk_user_hash   CHECK (PasswordHash LIKE '$2_$%' AND CHAR_LENGTH(PasswordHash) = 60),
  CONSTRAINT chk_user_role   CHECK (Role IN ('Operator','Technician','Manager','Admin')),
  CONSTRAINT chk_user_failed CHECK (FailedLogins BETWEEN 0 AND 5),
  CONSTRAINT fk_user_emp FOREIGN KEY (EmployeeID) REFERENCES Employee(EmployeeID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Technician  (Table 17) - one-to-one extension of Employee
-- ---------------------------------------------------------------------
CREATE TABLE Technician (
  TechnicianID   INT          NOT NULL AUTO_INCREMENT,
  EmployeeID     CHAR(5)      NOT NULL,
  IsAvailable    BOOLEAN      NOT NULL DEFAULT TRUE,
  CONSTRAINT pk_technician PRIMARY KEY (TechnicianID),
  CONSTRAINT uq_technician_emp UNIQUE (EmployeeID),
  CONSTRAINT fk_tech_emp FOREIGN KEY (EmployeeID) REFERENCES Employee(EmployeeID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ProblemType  (Table 18)
-- ---------------------------------------------------------------------
CREATE TABLE ProblemType (
  ProblemTypeID  INT          NOT NULL AUTO_INCREMENT,
  TypeName       VARCHAR(50)  NOT NULL,
  FaultCategory  VARCHAR(8)   NOT NULL,
  CONSTRAINT pk_problemtype PRIMARY KEY (ProblemTypeID),
  CONSTRAINT uq_problemtype_name UNIQUE (TypeName),
  CONSTRAINT chk_fault_cat CHECK (FaultCategory IN ('Hardware','Software'))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TechnicianSpecialism  (Table 19) - resolves Technician M:N ProblemType
-- ---------------------------------------------------------------------
CREATE TABLE TechnicianSpecialism (
  TechnicianID   INT          NOT NULL,
  ProblemTypeID  INT          NOT NULL,
  CONSTRAINT pk_techspec PRIMARY KEY (TechnicianID, ProblemTypeID),
  CONSTRAINT fk_ts_tech FOREIGN KEY (TechnicianID)
    REFERENCES Technician(TechnicianID) ON DELETE CASCADE,
  CONSTRAINT fk_ts_type FOREIGN KEY (ProblemTypeID)
    REFERENCES ProblemType(ProblemTypeID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Manufacturer  (Table 20)
-- ---------------------------------------------------------------------
CREATE TABLE Manufacturer (
  ManufacturerID   INT          NOT NULL AUTO_INCREMENT,
  ManufacturerName VARCHAR(60)  NOT NULL,
  SupportPhone     VARCHAR(20)  NULL,
  Website          VARCHAR(100) NULL,
  CONSTRAINT pk_manufacturer PRIMARY KEY (ManufacturerID),
  CONSTRAINT uq_manufacturer_name UNIQUE (ManufacturerName),
  CONSTRAINT chk_man_phone CHECK (SupportPhone IS NULL OR SupportPhone REGEXP '^[0-9+ -]{7,20}$'),
  CONSTRAINT chk_man_web   CHECK (Website IS NULL OR Website REGEXP '^https?://')
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- EquipmentType  (Table 21)
-- ---------------------------------------------------------------------
CREATE TABLE EquipmentType (
  EquipmentTypeID  INT          NOT NULL AUTO_INCREMENT,
  TypeName         VARCHAR(40)  NOT NULL,
  HardwareCategory VARCHAR(7)   NOT NULL,
  CONSTRAINT pk_equiptype PRIMARY KEY (EquipmentTypeID),
  CONSTRAINT uq_equiptype_name UNIQUE (TypeName),
  CONSTRAINT chk_hw_cat CHECK (HardwareCategory IN ('Office','Network'))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Hardware  (Table 22) - equipment register
-- ---------------------------------------------------------------------
CREATE TABLE Hardware (
  HardwareID           INT          NOT NULL AUTO_INCREMENT,
  SerialNumber         VARCHAR(30)  NOT NULL,
  EquipmentTypeID      INT          NOT NULL,
  ManufacturerID       INT          NOT NULL,
  Make                 VARCHAR(40)  NOT NULL,
  Model                VARCHAR(40)  NOT NULL,
  PurchaseDate         DATE         NOT NULL,
  WarrantyExpiry       DATE         NOT NULL,
  OfficeID             INT          NOT NULL,
  AssignedToEmployeeID CHAR(5)      NULL,
  CONSTRAINT pk_hardware PRIMARY KEY (HardwareID),
  CONSTRAINT uq_hardware_serial UNIQUE (SerialNumber),
  CONSTRAINT chk_hw_serial   CHECK (SerialNumber REGEXP '^[A-Za-z0-9-]+$'),
  CONSTRAINT chk_hw_warranty CHECK (WarrantyExpiry >= PurchaseDate),
  CONSTRAINT fk_hw_type   FOREIGN KEY (EquipmentTypeID) REFERENCES EquipmentType(EquipmentTypeID),
  CONSTRAINT fk_hw_man    FOREIGN KEY (ManufacturerID)  REFERENCES Manufacturer(ManufacturerID),
  CONSTRAINT fk_hw_office FOREIGN KEY (OfficeID)        REFERENCES Office(OfficeID),
  CONSTRAINT fk_hw_emp    FOREIGN KEY (AssignedToEmployeeID) REFERENCES Employee(EmployeeID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Software  (Table 23)
-- ---------------------------------------------------------------------
CREATE TABLE Software (
  SoftwareID       INT          NOT NULL AUTO_INCREMENT,
  SoftwareName     VARCHAR(60)  NOT NULL,
  Version          VARCHAR(20)  NOT NULL,
  SoftwareCategory VARCHAR(20)  NOT NULL,
  ManufacturerID   INT          NOT NULL,
  CONSTRAINT pk_software PRIMARY KEY (SoftwareID),
  CONSTRAINT uq_software_name_ver UNIQUE (SoftwareName, Version),
  CONSTRAINT chk_sw_cat CHECK (SoftwareCategory IN
    ('Spreadsheet','Word Processor','Presentation','Operating System',
     'Time Management','Project Management')),
  CONSTRAINT fk_sw_man FOREIGN KEY (ManufacturerID) REFERENCES Manufacturer(ManufacturerID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- SoftwareLicence  (Table 24) - ExpiryDate NULL = perpetual
-- ---------------------------------------------------------------------
CREATE TABLE SoftwareLicence (
  LicenceID      INT          NOT NULL AUTO_INCREMENT,
  LicenceNumber  VARCHAR(40)  NOT NULL,
  SoftwareID     INT          NOT NULL,
  LicenceType    VARCHAR(12)  NOT NULL,
  Seats          SMALLINT     NOT NULL DEFAULT 1,
  StartDate      DATE         NOT NULL,
  ExpiryDate     DATE         NULL,
  CONSTRAINT pk_licence PRIMARY KEY (LicenceID),
  CONSTRAINT uq_licence_number UNIQUE (LicenceNumber),
  CONSTRAINT chk_lic_type  CHECK (LicenceType IN ('Perpetual','Subscription','Volume','OEM')),
  CONSTRAINT chk_lic_seats CHECK (Seats BETWEEN 1 AND 10000),
  CONSTRAINT chk_lic_dates CHECK (ExpiryDate IS NULL OR ExpiryDate >= StartDate),
  CONSTRAINT fk_lic_sw FOREIGN KEY (SoftwareID) REFERENCES Software(SoftwareID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- InstalledSoftware  (Table 25) - resolves Hardware M:N SoftwareLicence
-- ---------------------------------------------------------------------
CREATE TABLE InstalledSoftware (
  HardwareID     INT          NOT NULL,
  LicenceID      INT          NOT NULL,
  InstallDate    DATE         NOT NULL DEFAULT (CURRENT_DATE),
  CONSTRAINT pk_installed PRIMARY KEY (HardwareID, LicenceID),
  CONSTRAINT fk_inst_hw  FOREIGN KEY (HardwareID)
    REFERENCES Hardware(HardwareID) ON DELETE CASCADE,
  CONSTRAINT fk_inst_lic FOREIGN KEY (LicenceID)
    REFERENCES SoftwareLicence(LicenceID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Problem  (Table 26) - one row per problem number
-- ---------------------------------------------------------------------
CREATE TABLE Problem (
  ProblemNumber        INT          NOT NULL AUTO_INCREMENT,
  ReportedByEmployeeID CHAR(5)      NOT NULL,
  ProblemTypeID        INT          NOT NULL,
  HardwareID           INT          NULL,
  OSSoftwareID         INT          NULL,
  SoftwareID           INT          NULL,
  Description          TEXT         NOT NULL,
  Priority             VARCHAR(8)   NOT NULL DEFAULT 'Medium',
  Status               VARCHAR(6)   NOT NULL DEFAULT 'Open',
  DateTimeLogged       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  TechnicianID         INT          NULL,
  DateTimeAssigned     DATETIME     NULL,
  DateTimeResolved     DATETIME     NULL,
  ResolutionNotes      TEXT         NULL,
  TimeTakenHours       DECIMAL(5,2) NULL,
  ResolvedByUserID     INT          NULL,
  CONSTRAINT pk_problem PRIMARY KEY (ProblemNumber),
  CONSTRAINT chk_description CHECK (CHAR_LENGTH(TRIM(Description)) >= 10),
  CONSTRAINT chk_priority    CHECK (Priority IN ('Low','Medium','High','Critical')),
  CONSTRAINT chk_status      CHECK (Status IN ('Open','Closed')),
  CONSTRAINT chk_hours       CHECK (TimeTakenHours BETWEEN 0.25 AND 500),
  CONSTRAINT chk_resolved    CHECK (DateTimeResolved IS NULL OR DateTimeResolved > DateTimeLogged),
  CONSTRAINT chk_closed      CHECK (Status = 'Open' OR (DateTimeResolved IS NOT NULL
                                    AND ResolutionNotes IS NOT NULL
                                    AND CHAR_LENGTH(TRIM(ResolutionNotes)) > 0
                                    AND TimeTakenHours IS NOT NULL)),
  CONSTRAINT fk_prob_reporter FOREIGN KEY (ReportedByEmployeeID) REFERENCES Employee(EmployeeID),
  CONSTRAINT fk_prob_type     FOREIGN KEY (ProblemTypeID)    REFERENCES ProblemType(ProblemTypeID),
  CONSTRAINT fk_prob_hw       FOREIGN KEY (HardwareID)       REFERENCES Hardware(HardwareID),
  CONSTRAINT fk_prob_os       FOREIGN KEY (OSSoftwareID)     REFERENCES Software(SoftwareID),
  CONSTRAINT fk_prob_sw       FOREIGN KEY (SoftwareID)       REFERENCES Software(SoftwareID),
  CONSTRAINT fk_prob_tech     FOREIGN KEY (TechnicianID)     REFERENCES Technician(TechnicianID),
  CONSTRAINT fk_prob_resolver FOREIGN KEY (ResolvedByUserID) REFERENCES AppUser(UserID),
  INDEX idx_problem_status (Status, TechnicianID),
  INDEX idx_problem_logged (DateTimeLogged)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- HelpdeskCall  (Table 27) - every call, new or follow-up
-- ---------------------------------------------------------------------
CREATE TABLE HelpdeskCall (
  CallID           INT          NOT NULL AUTO_INCREMENT,
  ProblemNumber    INT          NOT NULL,
  CallerEmployeeID CHAR(5)      NOT NULL,
  OperatorUserID   INT          NOT NULL,
  CallDateTime     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CallType         VARCHAR(9)   NOT NULL,
  CallReason       VARCHAR(255) NOT NULL,
  UrgencyLevel     VARCHAR(10)  NOT NULL DEFAULT 'Normal',
  Notes            TEXT         NULL,
  CONSTRAINT pk_call PRIMARY KEY (CallID),
  CONSTRAINT chk_call_type    CHECK (CallType IN ('New','Follow-up')),
  CONSTRAINT chk_call_reason  CHECK (CHAR_LENGTH(TRIM(CallReason)) >= 3),
  CONSTRAINT chk_call_urgency CHECK (UrgencyLevel IN ('Normal','Urgent','Distressed')),
  CONSTRAINT fk_call_problem  FOREIGN KEY (ProblemNumber)    REFERENCES Problem(ProblemNumber),
  CONSTRAINT fk_call_caller   FOREIGN KEY (CallerEmployeeID) REFERENCES Employee(EmployeeID),
  CONSTRAINT fk_call_operator FOREIGN KEY (OperatorUserID)   REFERENCES AppUser(UserID),
  INDEX idx_call_datetime (CallDateTime)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- MaintenanceLog  (Table 28) - audit of backup / restore / compact /
-- integrity check. UserID NULL = the scheduled nightly backup (Table 41).
-- ---------------------------------------------------------------------
CREATE TABLE MaintenanceLog (
  LogID          INT          NOT NULL AUTO_INCREMENT,
  UserID         INT          NULL,
  ActionType     VARCHAR(8)   NOT NULL,
  ActionDateTime DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  BackupFile     VARCHAR(255) NULL,
  Outcome        VARCHAR(7)   NOT NULL,
  CONSTRAINT pk_maintlog PRIMARY KEY (LogID),
  CONSTRAINT chk_maint_action  CHECK (ActionType IN ('Backup','Restore','Compact','Check')),
  CONSTRAINT chk_maint_system  CHECK (UserID IS NOT NULL OR ActionType = 'Backup'),
  CONSTRAINT chk_maint_outcome CHECK (Outcome IN ('Success','Failed')),
  CONSTRAINT fk_maint_user FOREIGN KEY (UserID) REFERENCES AppUser(UserID)
) ENGINE=InnoDB;

-- Quick check: should list 16 tables
SHOW TABLES;
