<?php
/**
 * JSON look-up used by Log New Call to auto-fill caller details (UR-02).
 * GET ?id=E0045 -> {EmployeeID, JobTitle, DepartmentName, OfficeName}
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('call.log');

header('Content-Type: application/json');
$row = Database::row(
    "SELECT e.EmployeeID, e.JobTitle, d.DepartmentName, o.OfficeName
       FROM Employee e
       JOIN Department d ON d.DepartmentID = e.DepartmentID
       JOIN Office o     ON o.OfficeID     = e.OfficeID
      WHERE e.EmployeeID = ? AND (e.EndDate IS NULL OR e.EndDate >= CURRENT_DATE)",
    [input('id')]);
echo json_encode($row ?? ['found' => false]);
