<?php
/**
 * Software & Licences register (UR-04, UR-11).
 * Software products and their licences; licence status (Valid / Expired)
 * and seats used are calculated by vw_LicenceStatus.
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('registers.view');
$canEdit = Auth::can('registers.edit');

$errors = [];
$sw  = ['SoftwareID' => '', 'SoftwareName' => '', 'Version' => '', 'SoftwareCategory' => '', 'ManufacturerID' => ''];
$lic = ['LicenceID' => '', 'LicenceNumber' => '', 'SoftwareID' => '', 'LicenceType' => '', 'Seats' => '1',
        'StartDate' => '', 'ExpiryDate' => ''];
$form = input('form');          // 'software' or 'licence' when a form is open

if (is_post()) {
    Auth::requirePermission('registers.edit');
    Csrf::check();

    if ($form === 'software') {
        foreach ($sw as $k => $_) { $sw[$k] = input($k); }
        $v = new Validator($sw);
        $v->required('SoftwareName', 'Please enter the software name.')->maxLength('SoftwareName', 60, 'Maximum 60 characters.')
          ->required('Version', 'Please enter the version.')->maxLength('Version', 20, 'Maximum 20 characters.')
          ->required('SoftwareCategory', 'Please choose the category.')
          ->inList('SoftwareCategory', Lookup::SW_CATEGORIES, 'Choose a category from the list.')
          ->required('ManufacturerID', 'Please choose the publisher.');
        if ($v->fails()) {
            $errors = $v->errors();
        } else {
            $vals = [$sw['SoftwareName'], $sw['Version'], $sw['SoftwareCategory'], (int)$sw['ManufacturerID']];
            try {
                ctype_digit($sw['SoftwareID'])
                    ? Database::exec('UPDATE Software SET SoftwareName = ?, Version = ?, SoftwareCategory = ?, ManufacturerID = ?
                                      WHERE SoftwareID = ?', [...$vals, (int)$sw['SoftwareID']])
                    : Database::exec('INSERT INTO Software (SoftwareName, Version, SoftwareCategory, ManufacturerID)
                                      VALUES (?, ?, ?, ?)', $vals);
                flash('success', 'Software saved.');
                redirect('registers/software.php');
            } catch (PDOException $e) {
                $errors['_form'] = DbError::message($e);
            }
        }
    } elseif ($form === 'licence') {
        foreach ($lic as $k => $_) { $lic[$k] = input($k); }
        $v = new Validator($lic);
        $v->required('LicenceNumber', 'Please enter the licence number.')->maxLength('LicenceNumber', 40, 'Maximum 40 characters.')
          ->required('SoftwareID', 'Please choose the software.')
          ->required('LicenceType', 'Please choose the licence type.')
          ->inList('LicenceType', Lookup::LICENCE_TYPES, 'Choose a licence type from the list.')
          ->required('Seats', 'Please enter the number of seats.')->integer('Seats', 1, 10000, 'Seats must be 1 to 10,000.')
          ->required('StartDate', 'Please enter the start date.')->date('StartDate', 'Enter a valid date.')
          ->date('ExpiryDate', 'Enter a valid date.')
          ->rule($lic['ExpiryDate'] === '' || $lic['ExpiryDate'] >= $lic['StartDate'], 'ExpiryDate', 'Expiry cannot be before the start date.');
        if ($v->fails()) {
            $errors = $v->errors();
        } else {
            $vals = [$lic['LicenceNumber'], (int)$lic['SoftwareID'], $lic['LicenceType'], (int)$lic['Seats'],
                     $lic['StartDate'], nullable($lic['ExpiryDate'])];
            try {
                ctype_digit($lic['LicenceID'])
                    ? Database::exec('UPDATE SoftwareLicence SET LicenceNumber = ?, SoftwareID = ?, LicenceType = ?, Seats = ?,
                                      StartDate = ?, ExpiryDate = ? WHERE LicenceID = ?', [...$vals, (int)$lic['LicenceID']])
                    : Database::exec('INSERT INTO SoftwareLicence (LicenceNumber, SoftwareID, LicenceType, Seats, StartDate, ExpiryDate)
                                      VALUES (?, ?, ?, ?, ?, ?)', $vals);
                flash('success', 'Licence saved.');
                redirect('registers/software.php');
            } catch (PDOException $e) {
                $errors['_form'] = DbError::message($e);
            }
        }
    }
} elseif ($canEdit && ctype_digit(input('edit_sw'))) {
    $form = 'software';
    $sw = Database::row('SELECT * FROM Software WHERE SoftwareID = ?', [(int)input('edit_sw')]) ?? $sw;
} elseif ($canEdit && ctype_digit(input('edit_lic'))) {
    $form = 'licence';
    $lic = Database::row('SELECT * FROM SoftwareLicence WHERE LicenceID = ?', [(int)input('edit_lic')]) ?? $lic;
}

$products = Database::rows("SELECT s.*, m.ManufacturerName,
                                   (SELECT COUNT(*) FROM vw_LicenceStatus l WHERE l.SoftwareID = s.SoftwareID AND l.LicenceStatus = 'Valid') AS ValidLicences
                              FROM Software s JOIN Manufacturer m ON m.ManufacturerID = s.ManufacturerID
                             ORDER BY s.SoftwareCategory, s.SoftwareName");
$licences = Database::rows('SELECT * FROM vw_LicenceStatus ORDER BY SoftwareName, LicenceNumber');

render('header', ['title' => 'Software & Licences']);
?>
<?php if ($canEdit && $form === 'software'): ?>
<form method="post" class="card">
  <?= Csrf::field() ?><input type="hidden" name="form" value="software"><input type="hidden" name="SoftwareID" value="<?= e($sw['SoftwareID']) ?>">
  <h3><?= $sw['SoftwareID'] ? 'Edit software' : 'Add software' ?></h3>
  <?php if (isset($errors['_form'])): ?><div class="flash flash-error"><?= e($errors['_form']) ?></div><?php endif; ?>
  <div class="grid grid-4">
    <?= form_field('SoftwareName', 'Name', $sw['SoftwareName'], $errors, 'text', ['required' => true, 'maxlength' => 60]) ?>
    <?= form_field('Version', 'Version', $sw['Version'], $errors, 'text', ['required' => true, 'maxlength' => 20]) ?>
    <?= form_field('SoftwareCategory', 'Category', $sw['SoftwareCategory'], $errors, 'select', ['required' => true], Lookup::same(Lookup::SW_CATEGORIES)) ?>
    <?= form_field('ManufacturerID', 'Publisher', $sw['ManufacturerID'], $errors, 'select', ['required' => true], Lookup::manufacturers()) ?>
  </div>
  <div class="actions"><a class="btn" href="<?= e(url('registers/software.php')) ?>">Cancel</a><button class="btn btn-primary">Save</button></div>
</form>
<?php elseif ($canEdit && $form === 'licence'): ?>
<form method="post" class="card">
  <?= Csrf::field() ?><input type="hidden" name="form" value="licence"><input type="hidden" name="LicenceID" value="<?= e($lic['LicenceID']) ?>">
  <h3><?= $lic['LicenceID'] ? 'Edit licence' : 'Add licence' ?></h3>
  <?php if (isset($errors['_form'])): ?><div class="flash flash-error"><?= e($errors['_form']) ?></div><?php endif; ?>
  <div class="grid grid-3">
    <?= form_field('LicenceNumber', 'Licence number', $lic['LicenceNumber'], $errors, 'text', ['required' => true, 'maxlength' => 40]) ?>
    <?= form_field('SoftwareID', 'Software', $lic['SoftwareID'], $errors, 'select', ['required' => true], Lookup::allSoftware()) ?>
    <?= form_field('LicenceType', 'Licence type', $lic['LicenceType'], $errors, 'select', ['required' => true], Lookup::same(Lookup::LICENCE_TYPES)) ?>
    <?= form_field('Seats', 'Seats', $lic['Seats'], $errors, 'number', ['required' => true, 'min' => 1, 'max' => 10000]) ?>
    <?= form_field('StartDate', 'Start date', $lic['StartDate'], $errors, 'date', ['required' => true]) ?>
    <?= form_field('ExpiryDate', 'Expiry date (blank = perpetual)', $lic['ExpiryDate'], $errors, 'date') ?>
  </div>
  <div class="actions"><a class="btn" href="<?= e(url('registers/software.php')) ?>">Cancel</a><button class="btn btn-primary">Save</button></div>
</form>
<?php endif; ?>

<section class="card">
  <div class="actions" style="margin-top:0;justify-content:space-between">
    <h3>Software products</h3>
    <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= e(url('registers/software.php', ['form' => 'software'])) ?>">Add software</a><?php endif; ?>
  </div>
  <table class="data">
    <tr><th>Software</th><th>Version</th><th>Category</th><th>Publisher</th><th>Licence</th><?= $canEdit ? '<th></th>' : '' ?></tr>
    <?php foreach ($products as $p): ?>
    <tr><td><?= e($p['SoftwareName']) ?></td><td><?= e($p['Version']) ?></td><td><?= e($p['SoftwareCategory']) ?></td>
      <td><?= e($p['ManufacturerName']) ?></td><td><?= badge($p['ValidLicences'] > 0 ? 'Valid' : 'No valid licence') ?></td>
      <?php if ($canEdit): ?><td><a class="btn btn-small" href="<?= e(url('registers/software.php', ['edit_sw' => $p['SoftwareID']])) ?>">Edit</a></td><?php endif; ?></tr>
    <?php endforeach; ?>
  </table>
</section>

<section class="card">
  <div class="actions" style="margin-top:0;justify-content:space-between">
    <h3>Licences</h3>
    <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= e(url('registers/software.php', ['form' => 'licence'])) ?>">Add licence</a><?php endif; ?>
  </div>
  <table class="data">
    <tr><th>Licence no.</th><th>Software</th><th>Type</th><th>Start</th><th>Expiry</th><th class="num">Seats used / total</th><th>Status</th><?= $canEdit ? '<th></th>' : '' ?></tr>
    <?php foreach ($licences as $l): ?>
    <tr><td><?= e($l['LicenceNumber']) ?></td><td><?= e($l['SoftwareName'] . ' ' . $l['Version']) ?></td><td><?= e($l['LicenceType']) ?></td>
      <td><?= e(fmt_date($l['StartDate'])) ?></td><td><?= e($l['ExpiryDate'] ? fmt_date($l['ExpiryDate']) : 'Perpetual') ?></td>
      <td class="num"><?= (int)$l['SeatsUsed'] ?> / <?= (int)$l['Seats'] ?></td><td><?= badge($l['LicenceStatus']) ?></td>
      <?php if ($canEdit): ?><td><a class="btn btn-small" href="<?= e(url('registers/software.php', ['edit_lic' => $l['LicenceID']])) ?>">Edit</a></td><?php endif; ?></tr>
    <?php endforeach; ?>
  </table>
</section>
<?php render('footer'); ?>
