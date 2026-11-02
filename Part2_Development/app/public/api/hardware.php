<?php
/**
 * JSON look-up used by Log New Call: equipment type, make, model, warranty
 * status (UR-03) and installed software with licence status (UR-04).
 * GET ?serial=HP-5CG1234XYZ
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('call.log');

header('Content-Type: application/json');
$hw = Database::row('SELECT HardwareID, EquipmentType, ManufacturerName, Make, Model, WarrantyStatus
                       FROM vw_HardwareStatus WHERE SerialNumber = ?', [input('serial')]);
if ($hw === null) {
    echo json_encode(['found' => false]);
    exit;
}

$installed = Database::rows(
    "SELECT s.SoftwareID, s.SoftwareCategory, CONCAT(s.SoftwareName, ' ', s.Version) AS Name,
            CASE WHEN l.ExpiryDate IS NULL OR l.ExpiryDate >= CURRENT_DATE THEN 'Valid' ELSE 'Expired' END AS LicenceStatus
       FROM InstalledSoftware i
       JOIN SoftwareLicence l ON l.LicenceID = i.LicenceID
       JOIN Software s        ON s.SoftwareID = l.SoftwareID
      WHERE i.HardwareID = ?
      ORDER BY s.SoftwareCategory = 'Operating System' DESC, s.SoftwareName", [$hw['HardwareID']]);

$os = null;
foreach ($installed as $s) {
    if ($s['SoftwareCategory'] === 'Operating System') { $os = $s['SoftwareID']; break; }
}
$hw['found']        = true;
$hw['OSSoftwareID'] = $os;
$hw['Installed']    = implode(', ', array_map(fn($s) => "{$s['Name']} ({$s['LicenceStatus']})", $installed)) ?: 'None recorded';
echo json_encode($hw);
