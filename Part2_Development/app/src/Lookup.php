<?php
/**
 * Lists used to fill drop-downs (recognition rather than recall,
 * Design Document Section 9.1). Each returns [value => label].
 */
declare(strict_types=1);

final class Lookup
{
    public const PRIORITIES      = ['Low', 'Medium', 'High', 'Critical'];
    public const URGENCY         = ['Normal', 'Urgent', 'Distressed'];
    public const SPECIALISATIONS = ['Land Development', 'Property Management', 'Brokerage', 'Lending',
                                    'Legal', 'Interior Design', 'Construction'];
    public const LICENCE_TYPES   = ['Perpetual', 'Subscription', 'Volume', 'OEM'];
    public const SW_CATEGORIES   = ['Spreadsheet', 'Word Processor', 'Presentation', 'Operating System',
                                    'Time Management', 'Project Management'];

    private static function pairs(string $sql, array $params = []): array
    {
        $out = [];
        foreach (Database::rows($sql, $params) as $r) {
            $out[$r['k']] = $r['v'];
        }
        return $out;
    }

    /** Current employees only - ex-employees cannot be callers (BR-04). */
    public static function currentEmployees(): array
    {
        return self::pairs("SELECT EmployeeID AS k,
                                   CONCAT(FirstName, ' ', LastName, ' (', EmployeeID, ')') AS v
                              FROM Employee
                             WHERE EndDate IS NULL OR EndDate >= CURRENT_DATE
                             ORDER BY FirstName, LastName");
    }

    public static function allEmployees(): array
    {
        return self::pairs("SELECT EmployeeID AS k,
                                   CONCAT(FirstName, ' ', LastName, ' (', EmployeeID, ')') AS v
                              FROM Employee ORDER BY FirstName, LastName");
    }

    public static function problemTypes(): array
    {
        return self::pairs('SELECT ProblemTypeID AS k, TypeName AS v FROM ProblemType ORDER BY FaultCategory, TypeName');
    }

    public static function offices(): array
    {
        return self::pairs("SELECT OfficeID AS k, CONCAT(OfficeName, ' - ', Specialisation) AS v
                              FROM Office ORDER BY OfficeName");
    }

    public static function departments(): array
    {
        return self::pairs('SELECT DepartmentID AS k, DepartmentName AS v FROM Department ORDER BY DepartmentName');
    }

    public static function technicians(): array
    {
        return self::pairs("SELECT t.TechnicianID AS k, CONCAT(e.FirstName, ' ', e.LastName) AS v
                              FROM Technician t JOIN Employee e ON e.EmployeeID = t.EmployeeID
                             ORDER BY e.FirstName");
    }

    public static function manufacturers(): array
    {
        return self::pairs('SELECT ManufacturerID AS k, ManufacturerName AS v FROM Manufacturer ORDER BY ManufacturerName');
    }

    public static function equipmentTypes(): array
    {
        return self::pairs("SELECT EquipmentTypeID AS k, CONCAT(TypeName, ' (', HardwareCategory, ' hardware)') AS v
                              FROM EquipmentType ORDER BY HardwareCategory, TypeName");
    }

    /** Operating systems or applications, with licence status in the label (UR-04). */
    public static function software(bool $operatingSystems): array
    {
        $op = $operatingSystems ? '=' : '<>';
        return self::pairs("SELECT s.SoftwareID AS k,
                                   CONCAT(s.SoftwareName, ' ', s.Version, ' - ',
                                     IF(EXISTS (SELECT 1 FROM vw_LicenceStatus l
                                                 WHERE l.SoftwareID = s.SoftwareID AND l.LicenceStatus = 'Valid'),
                                        'Valid licence', 'NO valid licence')) AS v
                              FROM Software s
                             WHERE s.SoftwareCategory $op 'Operating System'
                             ORDER BY s.SoftwareName, s.Version");
    }

    public static function allSoftware(): array
    {
        return self::pairs("SELECT SoftwareID AS k, CONCAT(SoftwareName, ' ', Version) AS v
                              FROM Software ORDER BY SoftwareName, Version");
    }

    public static function licences(): array
    {
        return self::pairs("SELECT LicenceID AS k, CONCAT(LicenceNumber, ' - ', SoftwareName, ' ', Version) AS v
                              FROM vw_LicenceStatus ORDER BY SoftwareName, LicenceNumber");
    }

    /** Pairs a plain list as value => value. */
    public static function same(array $list): array
    {
        return array_combine($list, $list);
    }
}
