<?php
/**
 * Employees register / personnel register (UR-10).
 * Ex-employees are never deleted: the administrator records an End Date,
 * so their past calls and problems stay in the reports (BR-03, FR-16).
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('registers.view');
$canEdit = Auth::can('registers.edit');

$errors = [];
$empty = ['EmployeeID' => '', 'FirstName' => '', 'LastName' => '', 'JobTitle' => '', 'ContactPhone' => '',
          'Email' => '', 'StartDate' => '', 'EndDate' => '', 'OfficeID' => '', 'DepartmentID' => ''];
$f = $empty;
$isNew = true;

if (is_post()) {
    Auth::requirePermission('registers.edit');
    Csrf::check();
    foreach ($f as $k => $_) {
        $f[$k] = input($k);
    }
    $f['EmployeeID'] = strtoupper($f['EmployeeID']);
    $isNew = input('mode') !== 'edit';

    $name = "/^[A-Za-z][A-Za-z' \-]*$/";
    $v = new Validator($f);
    $v->required('EmployeeID', 'Please enter the employee ID.')
      ->pattern('EmployeeID', '/^E\d{4}$/', 'Employee ID must look like E0045.')
      ->required('FirstName', 'Please enter the first name.')->maxLength('FirstName', 40, 'Maximum 40 characters.')
      ->pattern('FirstName', $name, "Letters, spaces, ' and - only.")
      ->required('LastName', 'Please enter the last name.')->maxLength('LastName', 40, 'Maximum 40 characters.')
      ->pattern('LastName', $name, "Letters, spaces, ' and - only.")
      ->required('JobTitle', 'Please enter the job title.')->maxLength('JobTitle', 50, 'Maximum 50 characters.')
      ->required('ContactPhone', 'Please enter the phone number.')
      ->pattern('ContactPhone', '/^[0-9+ \-]{7,15}$/', 'Enter a valid phone number.')
      ->required('Email', 'Please enter the email address.')
      ->email('Email', 'Enter a valid, unused email address.')->maxLength('Email', 100, 'Maximum 100 characters.')
      ->required('StartDate', 'Please enter the start date.')
      ->date('StartDate', 'Enter a valid date.')->notFuture('StartDate', 'Start date cannot be in the future.')
      ->date('EndDate', 'Enter a valid date.')
      ->rule($f['EndDate'] === '' || $f['StartDate'] === '' || $f['EndDate'] >= $f['StartDate'], 'EndDate', 'End date cannot be before start date.')
      ->required('OfficeID', 'Please choose the office.')
      ->required('DepartmentID', 'Please choose the department.');

    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        $vals = [$f['FirstName'], $f['LastName'], $f['JobTitle'], $f['ContactPhone'], $f['Email'],
                 $f['StartDate'], nullable($f['EndDate']), (int)$f['OfficeID'], (int)$f['DepartmentID']];
        try {
            if ($isNew) {
                Database::exec('INSERT INTO Employee (FirstName, LastName, JobTitle, ContactPhone, Email,
                                StartDate, EndDate, OfficeID, DepartmentID, EmployeeID)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$vals, $f['EmployeeID']]);
            } else {
                Database::exec('UPDATE Employee SET FirstName = ?, LastName = ?, JobTitle = ?, ContactPhone = ?,
                                Email = ?, StartDate = ?, EndDate = ?, OfficeID = ?, DepartmentID = ?
                                WHERE EmployeeID = ?', [...$vals, $f['EmployeeID']]);
            }
            flash('success', "Employee {$f['EmployeeID']} saved.");
            redirect('registers/employees.php');
        } catch (PDOException $e) {
            $errors['_form'] = DbError::message($e);
        }
    }
} elseif ($canEdit && input('edit') !== '') {
    $row = Database::row('SELECT * FROM Employee WHERE EmployeeID = ?', [input('edit')]);
    if ($row) {
        $f = $row;
        $isNew = false;
    }
}

// list with filters
$q       = input('q');
$office  = input('office');
$showEx  = input('ex') === '1';
$sql = "SELECT e.*, o.OfficeName, d.DepartmentName
          FROM Employee e JOIN Office o ON o.OfficeID = e.OfficeID
          JOIN Department d ON d.DepartmentID = e.DepartmentID WHERE 1 = 1";
$params = [];
if ($q !== '') {
    $sql .= " AND (CONCAT(e.FirstName, ' ', e.LastName) LIKE ? OR e.EmployeeID = ?)";
    array_push($params, '%' . $q . '%', strtoupper($q));
}
if (ctype_digit($office)) {
    $sql .= ' AND e.OfficeID = ?';
    $params[] = (int)$office;
}
if (!$showEx) {
    $sql .= ' AND (e.EndDate IS NULL OR e.EndDate >= CURRENT_DATE)';
}
$employees = Database::rows($sql . ' ORDER BY e.LastName, e.FirstName', $params);
$showForm = $canEdit && (input('new') === '1' || input('edit') !== '' || $errors);

render('header', ['title' => 'Employees']);
?>
<?php if ($showForm): ?>
<form method="post" class="card">
  <?= Csrf::field() ?>
  <input type="hidden" name="mode" value="<?= $isNew ? 'new' : 'edit' ?>">
  <h3><?= $isNew ? 'Add employee' : 'Edit employee ' . e($f['EmployeeID']) ?></h3>
  <?php if (isset($errors['_form'])): ?><div class="flash flash-error"><?= e($errors['_form']) ?></div><?php endif; ?>
  <div class="grid grid-3">
    <?= form_field('EmployeeID', 'Employee ID', $f['EmployeeID'], $errors, 'text',
        ['required' => true, 'maxlength' => 5, 'pattern' => 'E[0-9]{4}', 'placeholder' => 'E0045', 'readonly' => !$isNew]) ?>
    <?= form_field('FirstName', 'First name', $f['FirstName'], $errors, 'text', ['required' => true, 'maxlength' => 40]) ?>
    <?= form_field('LastName', 'Last name', $f['LastName'], $errors, 'text', ['required' => true, 'maxlength' => 40]) ?>
    <?= form_field('JobTitle', 'Job title', $f['JobTitle'], $errors, 'text', ['required' => true, 'maxlength' => 50]) ?>
    <?= form_field('ContactPhone', 'Contact phone', $f['ContactPhone'], $errors, 'text', ['required' => true, 'maxlength' => 15]) ?>
    <?= form_field('Email', 'Email', $f['Email'], $errors, 'email', ['required' => true, 'maxlength' => 100]) ?>
    <?= form_field('OfficeID', 'Office', $f['OfficeID'], $errors, 'select', ['required' => true], Lookup::offices()) ?>
    <?= form_field('DepartmentID', 'Department', $f['DepartmentID'], $errors, 'select', ['required' => true], Lookup::departments()) ?>
    <div></div>
    <?= form_field('StartDate', 'Employment start date', $f['StartDate'], $errors, 'date', ['required' => true, 'max' => date('Y-m-d')]) ?>
    <?= form_field('EndDate', 'End date (leave blank if current)', $f['EndDate'], $errors, 'date') ?>
  </div>
  <p class="hint">Employees who leave are not deleted - enter an end date so their history is kept.</p>
  <div class="actions">
    <a class="btn" href="<?= e(url('registers/employees.php')) ?>">Cancel</a>
    <button class="btn btn-primary">Save</button>
  </div>
</form>
<?php endif; ?>

<section class="card">
  <form class="toolbar" method="get">
    <div><label for="q">Name or ID</label><input id="q" name="q" value="<?= e($q) ?>"></div>
    <div><label for="office">Office</label><select id="office" name="office"><?= options(Lookup::offices(), $office, 'All offices') ?></select></div>
    <div><label style="font-weight:normal"><input type="checkbox" name="ex" value="1" style="width:auto" <?= $showEx ? 'checked' : '' ?>> Include ex-employees</label></div>
    <button class="btn btn-primary">Search</button>
    <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= e(url('registers/employees.php', ['new' => 1])) ?>">Add employee</a><?php endif; ?>
  </form>
  <table class="data">
    <tr><th>ID</th><th>Name</th><th>Job title</th><th>Department</th><th>Office</th><th>Phone</th><th>Email</th><th>Status</th><?= $canEdit ? '<th></th>' : '' ?></tr>
    <?php foreach ($employees as $emp): $left = $emp['EndDate'] !== null && $emp['EndDate'] < date('Y-m-d'); ?>
    <tr>
      <td><?= e($emp['EmployeeID']) ?></td><td><?= e($emp['FirstName'] . ' ' . $emp['LastName']) ?></td>
      <td><?= e($emp['JobTitle']) ?></td><td><?= e($emp['DepartmentName']) ?></td><td><?= e($emp['OfficeName']) ?></td>
      <td><?= e($emp['ContactPhone']) ?></td><td><?= e($emp['Email']) ?></td>
      <td><?= badge($left ? 'Left ' . fmt_date($emp['EndDate']) : 'Current') ?></td>
      <?php if ($canEdit): ?><td><a class="btn btn-small" href="<?= e(url('registers/employees.php', ['edit' => $emp['EmployeeID']])) ?>">Edit</a></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php render('footer'); ?>
