<?php
/**
 * Offices register (UR-10): name, address, phone and specialisation.
 * View: everyone. Add / edit / delete: System Administrator.
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('registers.view');
$canEdit = Auth::can('registers.edit');

$errors = [];
$empty = ['OfficeID' => '', 'OfficeName' => '', 'AddressLine' => '', 'City' => '', 'Postcode' => '',
          'ContactPhone' => '', 'Specialisation' => ''];
$f = $empty;

if (is_post()) {
    Auth::requirePermission('registers.edit');
    Csrf::check();
    $id = input('OfficeID');

    if (input('action') === 'delete' && ctype_digit($id)) {
        try {
            Database::exec('DELETE FROM Office WHERE OfficeID = ?', [(int)$id]);
            flash('success', 'Office deleted.');
        } catch (PDOException $e) {
            flash('error', DbError::message($e));
        }
        redirect('registers/offices.php');
    }

    foreach ($f as $k => $_) {
        $f[$k] = input($k);
    }
    $v = new Validator($f);
    $v->required('OfficeName', 'Please enter the office name.')->maxLength('OfficeName', 60, 'Maximum 60 characters.')
      ->required('AddressLine', 'Please enter the address.')->maxLength('AddressLine', 100, 'Maximum 100 characters.')
      ->required('City', 'Please enter the city.')->maxLength('City', 40, 'Maximum 40 characters.')
      ->required('Postcode', 'Please enter the postcode.')->maxLength('Postcode', 10, 'Maximum 10 characters.')
      ->required('ContactPhone', 'Please enter the phone number.')
      ->pattern('ContactPhone', '/^[0-9+ \-]{7,15}$/', 'Enter a valid phone number (7-15 digits, spaces, + and -).')
      ->required('Specialisation', 'Please choose a specialisation.')
      ->inList('Specialisation', Lookup::SPECIALISATIONS, 'Choose a specialisation from the list.');

    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        $vals = [$f['OfficeName'], $f['AddressLine'], $f['City'], $f['Postcode'], $f['ContactPhone'], $f['Specialisation']];
        try {
            if (ctype_digit($id)) {
                Database::exec('UPDATE Office SET OfficeName = ?, AddressLine = ?, City = ?, Postcode = ?,
                                ContactPhone = ?, Specialisation = ? WHERE OfficeID = ?', [...$vals, (int)$id]);
            } else {
                Database::exec('INSERT INTO Office (OfficeName, AddressLine, City, Postcode, ContactPhone, Specialisation)
                                VALUES (?, ?, ?, ?, ?, ?)', $vals);
            }
            flash('success', 'Office saved.');
            redirect('registers/offices.php');
        } catch (PDOException $e) {
            $errors['_form'] = DbError::message($e);
        }
    }
} elseif ($canEdit && ctype_digit(input('edit'))) {
    $f = Database::row('SELECT * FROM Office WHERE OfficeID = ?', [(int)input('edit')]) ?? $empty;
}

$offices = Database::rows('SELECT o.*, (SELECT COUNT(*) FROM Employee e WHERE e.OfficeID = o.OfficeID
                                          AND (e.EndDate IS NULL OR e.EndDate >= CURRENT_DATE)) AS Staff
                             FROM Office o ORDER BY OfficeName');
$showForm = $canEdit && (input('new') === '1' || input('edit') !== '' || $errors);

render('header', ['title' => 'Offices']);
?>
<?php if ($showForm): ?>
<form method="post" class="card">
  <?= Csrf::field() ?>
  <input type="hidden" name="OfficeID" value="<?= e($f['OfficeID']) ?>">
  <h3><?= $f['OfficeID'] ? 'Edit office' : 'Add office' ?></h3>
  <?php if (isset($errors['_form'])): ?><div class="flash flash-error"><?= e($errors['_form']) ?></div><?php endif; ?>
  <div class="grid grid-3">
    <?= form_field('OfficeName', 'Office name', $f['OfficeName'], $errors, 'text', ['required' => true, 'maxlength' => 60]) ?>
    <?= form_field('Specialisation', 'Specialisation', $f['Specialisation'], $errors, 'select', ['required' => true], Lookup::same(Lookup::SPECIALISATIONS)) ?>
    <?= form_field('ContactPhone', 'Contact phone', $f['ContactPhone'], $errors, 'text', ['required' => true, 'maxlength' => 15, 'pattern' => '[0-9+ \-]{7,15}']) ?>
    <?= form_field('AddressLine', 'Address', $f['AddressLine'], $errors, 'text', ['required' => true, 'maxlength' => 100]) ?>
    <?= form_field('City', 'City', $f['City'], $errors, 'text', ['required' => true, 'maxlength' => 40]) ?>
    <?= form_field('Postcode', 'Postcode', $f['Postcode'], $errors, 'text', ['required' => true, 'maxlength' => 10]) ?>
  </div>
  <div class="actions">
    <a class="btn" href="<?= e(url('registers/offices.php')) ?>">Cancel</a>
    <button class="btn btn-primary">Save</button>
  </div>
</form>
<?php endif; ?>

<section class="card">
  <div class="actions" style="margin-top:0;justify-content:space-between">
    <h3><?= count($offices) ?> offices</h3>
    <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= e(url('registers/offices.php', ['new' => 1])) ?>">Add office</a><?php endif; ?>
  </div>
  <table class="data">
    <tr><th>Office</th><th>Specialisation</th><th>Address</th><th>Phone</th><th class="num">Current staff</th><?= $canEdit ? '<th></th>' : '' ?></tr>
    <?php foreach ($offices as $o): ?>
    <tr>
      <td><?= e($o['OfficeName']) ?></td><td><?= e($o['Specialisation']) ?></td>
      <td><?= e($o['AddressLine'] . ', ' . $o['City'] . ' ' . $o['Postcode']) ?></td>
      <td><?= e($o['ContactPhone']) ?></td><td class="num"><?= (int)$o['Staff'] ?></td>
      <?php if ($canEdit): ?>
      <td>
        <a class="btn btn-small" href="<?= e(url('registers/offices.php', ['edit' => $o['OfficeID']])) ?>">Edit</a>
        <form method="post" style="display:inline" data-confirm="Delete this office?">
          <?= Csrf::field() ?><input type="hidden" name="OfficeID" value="<?= (int)$o['OfficeID'] ?>">
          <button class="btn btn-small btn-danger" name="action" value="delete">Delete</button>
        </form>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php render('footer'); ?>
