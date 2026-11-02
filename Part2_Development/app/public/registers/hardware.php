<?php
/**
 * Equipment register - Hardware (Design Document Figure 11, UR-11).
 * Shows warranty status; the administrator can add/edit items and manage
 * the software installed on each computer (InstalledSoftware link table).
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('registers.view');
$canEdit = Auth::can('registers.edit');

$errors = [];
$empty = ['HardwareID' => '', 'SerialNumber' => '', 'EquipmentTypeID' => '', 'ManufacturerID' => '', 'Make' => '',
          'Model' => '', 'PurchaseDate' => '', 'WarrantyExpiry' => '', 'OfficeID' => '', 'AssignedToEmployeeID' => ''];
$f = $empty;

if (is_post()) {
    Auth::requirePermission('registers.edit');
    Csrf::check();
    $id = input('HardwareID');
    $action = input('action');

    // --- installed software: add / remove a licence on this computer
    if (in_array($action, ['install', 'uninstall'], true) && ctype_digit($id) && ctype_digit(input('LicenceID'))) {
        try {
            if ($action === 'install') {
                Database::exec('INSERT INTO InstalledSoftware (HardwareID, LicenceID) VALUES (?, ?)', [(int)$id, (int)input('LicenceID')]);
            } else {
                Database::exec('DELETE FROM InstalledSoftware WHERE HardwareID = ? AND LicenceID = ?', [(int)$id, (int)input('LicenceID')]);
            }
            flash('success', 'Installed software updated.');
        } catch (PDOException $e) {
            flash('error', DbError::message($e));
        }
        redirect('registers/hardware.php', ['edit' => $id]);
    }

    if ($action === 'delete' && ctype_digit($id)) {
        try {
            Database::exec('DELETE FROM Hardware WHERE HardwareID = ?', [(int)$id]);
            flash('success', 'Hardware item deleted.');
        } catch (PDOException $e) {
            flash('error', DbError::message($e) . ' Items with logged problems are kept for the reports.');
        }
        redirect('registers/hardware.php');
    }

    // --- save item
    foreach ($f as $k => $_) {
        $f[$k] = input($k);
    }
    $v = new Validator($f);
    $v->required('SerialNumber', 'Please enter the serial number.')
      ->pattern('SerialNumber', '/^[A-Za-z0-9\-]{1,30}$/', 'Serial number: letters, digits and - only (max 30).')
      ->required('EquipmentTypeID', 'Please choose the equipment type.')
      ->required('ManufacturerID', 'Please choose the manufacturer.')
      ->required('Make', 'Please enter the make.')->maxLength('Make', 40, 'Maximum 40 characters.')
      ->required('Model', 'Please enter the model.')->maxLength('Model', 40, 'Maximum 40 characters.')
      ->required('PurchaseDate', 'Please enter the purchase date.')
      ->date('PurchaseDate', 'Enter a valid date.')->notFuture('PurchaseDate', 'Purchase date cannot be in the future.')
      ->required('WarrantyExpiry', 'Please enter the warranty expiry date.')->date('WarrantyExpiry', 'Enter a valid date.')
      ->rule($f['WarrantyExpiry'] === '' || $f['WarrantyExpiry'] >= $f['PurchaseDate'], 'WarrantyExpiry', 'Warranty cannot end before purchase.')
      ->required('OfficeID', 'Please choose the office.');

    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        $vals = [$f['SerialNumber'], (int)$f['EquipmentTypeID'], (int)$f['ManufacturerID'], $f['Make'], $f['Model'],
                 $f['PurchaseDate'], $f['WarrantyExpiry'], (int)$f['OfficeID'], nullable($f['AssignedToEmployeeID'])];
        try {
            if (ctype_digit($id)) {
                Database::exec('UPDATE Hardware SET SerialNumber = ?, EquipmentTypeID = ?, ManufacturerID = ?, Make = ?,
                                Model = ?, PurchaseDate = ?, WarrantyExpiry = ?, OfficeID = ?, AssignedToEmployeeID = ?
                                WHERE HardwareID = ?', [...$vals, (int)$id]);
            } else {
                Database::exec('INSERT INTO Hardware (SerialNumber, EquipmentTypeID, ManufacturerID, Make, Model,
                                PurchaseDate, WarrantyExpiry, OfficeID, AssignedToEmployeeID)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $vals);
                $id = (string)Database::current()->lastInsertId();
            }
            flash('success', "Hardware item {$f['SerialNumber']} saved.");
            redirect('registers/hardware.php', ['edit' => $id]);
        } catch (PDOException $e) {
            $errors['_form'] = DbError::message($e);
        }
    }
} elseif ($canEdit && ctype_digit(input('edit'))) {
    $f = Database::row('SELECT * FROM Hardware WHERE HardwareID = ?', [(int)input('edit')]) ?? $empty;
}

$installed = $f['HardwareID'] !== '' ? Database::rows(
    'SELECT l.LicenceID, l.LicenceNumber, l.SoftwareName, l.Version, l.LicenceStatus
       FROM InstalledSoftware i JOIN vw_LicenceStatus l ON l.LicenceID = i.LicenceID
      WHERE i.HardwareID = ? ORDER BY l.SoftwareName', [(int)$f['HardwareID']]) : [];

$q = input('q');
$list = Database::rows("SELECT * FROM vw_HardwareStatus
                         WHERE ? = '' OR SerialNumber LIKE ? OR Make LIKE ? OR Model LIKE ? OR OfficeName LIKE ?
                         ORDER BY HardwareCategory, EquipmentType, SerialNumber",
                       [$q, "%$q%", "%$q%", "%$q%", "%$q%"]);
$showForm = $canEdit && (input('new') === '1' || input('edit') !== '' || $errors);

render('header', ['title' => 'Equipment Register - Hardware']);
?>
<?php if ($showForm): ?>
<form method="post" class="card">
  <?= Csrf::field() ?>
  <input type="hidden" name="HardwareID" value="<?= e($f['HardwareID']) ?>">
  <h3>Hardware item <?= $f['HardwareID'] ? '(register no. ' . e($f['HardwareID']) . ')' : '(new)' ?></h3>
  <?php if (isset($errors['_form'])): ?><div class="flash flash-error"><?= e($errors['_form']) ?></div><?php endif; ?>
  <div class="grid grid-3">
    <?= form_field('SerialNumber', 'Serial number', $f['SerialNumber'], $errors, 'text', ['required' => true, 'maxlength' => 30]) ?>
    <?= form_field('EquipmentTypeID', 'Equipment type', $f['EquipmentTypeID'], $errors, 'select', ['required' => true], Lookup::equipmentTypes()) ?>
    <?= form_field('ManufacturerID', 'Manufacturer', $f['ManufacturerID'], $errors, 'select', ['required' => true], Lookup::manufacturers()) ?>
    <?= form_field('Make', 'Make', $f['Make'], $errors, 'text', ['required' => true, 'maxlength' => 40, 'placeholder' => 'EliteBook']) ?>
    <?= form_field('Model', 'Model', $f['Model'], $errors, 'text', ['required' => true, 'maxlength' => 40, 'placeholder' => '840 G9']) ?>
    <div></div>
    <?= form_field('PurchaseDate', 'Purchase date', $f['PurchaseDate'], $errors, 'date', ['required' => true, 'max' => date('Y-m-d')]) ?>
    <?= form_field('WarrantyExpiry', 'Warranty expiry', $f['WarrantyExpiry'], $errors, 'date', ['required' => true]) ?>
    <div><label>Warranty status</label><?= $f['WarrantyExpiry'] ? badge($f['WarrantyExpiry'] >= date('Y-m-d') ? 'Under warranty' : 'Expired') : '' ?></div>
    <?= form_field('OfficeID', 'Office', $f['OfficeID'], $errors, 'select', ['required' => true], Lookup::offices()) ?>
    <?= form_field('AssignedToEmployeeID', 'Assigned to employee', $f['AssignedToEmployeeID'], $errors, 'select', [], Lookup::allEmployees()) ?>
  </div>
  <div class="actions">
    <a class="btn" href="<?= e(url('registers/hardware.php', ['new' => 1])) ?>">New</a>
    <button class="btn btn-primary">Save</button>
    <?php if ($f['HardwareID']): ?>
      <button class="btn btn-danger" name="action" value="delete" formnovalidate>Delete</button>
    <?php endif; ?>
  </div>
</form>

<?php if ($f['HardwareID']): ?>
<section class="card">
  <h3>Installed software</h3>
  <table class="data">
    <tr><th>Software</th><th>Licence</th><th>Status</th><th></th></tr>
    <?php foreach ($installed as $s): ?>
    <tr><td><?= e($s['SoftwareName'] . ' ' . $s['Version']) ?></td><td><?= e($s['LicenceNumber']) ?></td><td><?= badge($s['LicenceStatus']) ?></td>
      <td><form method="post" data-confirm="Remove this software?"><?= Csrf::field() ?>
        <input type="hidden" name="HardwareID" value="<?= e($f['HardwareID']) ?>"><input type="hidden" name="LicenceID" value="<?= (int)$s['LicenceID'] ?>">
        <button class="btn btn-small btn-danger" name="action" value="uninstall">Remove</button></form></td></tr>
    <?php endforeach; ?>
  </table>
  <form method="post" class="toolbar" style="margin-top:12px">
    <?= Csrf::field() ?><input type="hidden" name="HardwareID" value="<?= e($f['HardwareID']) ?>">
    <div><label for="LicenceID">Install licensed software</label>
      <select id="LicenceID" name="LicenceID" required><?= options(Lookup::licences(), null, '- choose licence -') ?></select></div>
    <button class="btn btn-primary" name="action" value="install">Add</button>
  </form>
</section>
<?php endif; endif; ?>

<section class="card">
  <form class="toolbar" method="get">
    <div><label for="q">Serial, make, model or office</label><input id="q" name="q" value="<?= e($q) ?>"></div>
    <button class="btn btn-primary">Search</button>
    <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= e(url('registers/hardware.php', ['new' => 1])) ?>">Add hardware</a><?php endif; ?>
  </form>
  <table class="data">
    <tr><th>Serial no.</th><th>Type</th><th>Category</th><th>Manufacturer / make / model</th><th>Office</th><th>Assigned to</th><th>Warranty</th><?= $canEdit ? '<th></th>' : '' ?></tr>
    <?php foreach ($list as $h): ?>
    <tr>
      <td><?= e($h['SerialNumber']) ?></td><td><?= e($h['EquipmentType']) ?></td><td><?= e($h['HardwareCategory']) ?></td>
      <td><?= e($h['ManufacturerName'] . ' ' . $h['Make'] . ' ' . $h['Model']) ?></td><td><?= e($h['OfficeName']) ?></td>
      <td><?= e($h['AssignedTo'] ?? '—') ?></td>
      <td><?= badge($h['WarrantyStatus']) ?> <span class="hint"><?= e(fmt_date($h['WarrantyExpiry'])) ?></span></td>
      <?php if ($canEdit): ?><td><a class="btn btn-small" href="<?= e(url('registers/hardware.php', ['edit' => $h['HardwareID']])) ?>">Edit</a></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php render('footer'); ?>
