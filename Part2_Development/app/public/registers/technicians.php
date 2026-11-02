<?php
/**
 * Technicians & Skills register. The administrator adds technicians, sets
 * their specialisms (TechnicianSpecialism link table, BR-06) and marks them
 * available or on leave. Specialisms drive the Assign Technician screen.
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('registers.view');
$canEdit = Auth::can('registers.edit');

$types = Lookup::problemTypes();
$error = null;

if (is_post()) {
    Auth::requirePermission('registers.edit');
    Csrf::check();
    $action = input('action');
    $techId = input('TechnicianID');
    $skills = array_values(array_filter((array)($_POST['skills'] ?? []), fn($s) => isset($types[$s])));

    if ($action === 'save' && ctype_digit($techId) && !$skills) {
        $error = 'A technician must have at least one specialism.';     // BR-06
    } elseif ($action === 'add' && (input('EmployeeID') === '' || !$skills)) {
        $error = 'Choose the employee and at least one specialism.';
    } else {
        $pdo = Database::current();
        try {
            $pdo->beginTransaction();                                     // all or nothing
            if ($action === 'add') {
                Database::exec('INSERT INTO Technician (EmployeeID, IsAvailable) VALUES (?, ?)',
                    [input('EmployeeID'), input('IsAvailable') === '1' ? 1 : 0]);
                $techId = (string)$pdo->lastInsertId();
            } else {
                Database::exec('UPDATE Technician SET IsAvailable = ? WHERE TechnicianID = ?',
                    [input('IsAvailable') === '1' ? 1 : 0, (int)$techId]);
                Database::exec('DELETE FROM TechnicianSpecialism WHERE TechnicianID = ?', [(int)$techId]);
            }
            foreach ($skills as $s) {
                Database::exec('INSERT INTO TechnicianSpecialism (TechnicianID, ProblemTypeID) VALUES (?, ?)', [(int)$techId, (int)$s]);
            }
            $pdo->commit();
            flash('success', 'Technician saved.');
            redirect('registers/technicians.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = DbError::message($e);
        }
    }
}

$techs = Database::rows("SELECT t.TechnicianID, t.EmployeeID, t.IsAvailable,
                                CONCAT(e.FirstName, ' ', e.LastName) AS Name, o.OfficeName,
                                (SELECT COUNT(*) FROM Problem p WHERE p.TechnicianID = t.TechnicianID AND p.Status = 'Open') AS OpenJobs
                           FROM Technician t JOIN Employee e ON e.EmployeeID = t.EmployeeID
                           JOIN Office o ON o.OfficeID = e.OfficeID ORDER BY Name");
$skillsOf = [];
foreach (Database::rows('SELECT TechnicianID, ProblemTypeID FROM TechnicianSpecialism') as $r) {
    $skillsOf[$r['TechnicianID']][] = (int)$r['ProblemTypeID'];
}
$editId = $canEdit && ctype_digit(input('edit')) ? (int)input('edit') : null;

/** Checkbox list of all problem types. */
$skillBoxes = function (array $checked) use ($types): string {
    $html = '<div class="grid grid-4" style="gap:2px 12px">';
    foreach ($types as $id => $name) {
        $html .= '<label style="font-weight:normal"><input type="checkbox" name="skills[]" value="' . (int)$id . '" style="width:auto"'
               . (in_array((int)$id, $checked, true) ? ' checked' : '') . '> ' . e($name) . '</label>';
    }
    return $html . '</div>';
};

render('header', ['title' => 'Technicians & Skills']);
?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($editId !== null):
    $t = current(array_filter($techs, fn($t) => (int)$t['TechnicianID'] === $editId)); ?>
<form method="post" class="card">
  <?= Csrf::field() ?><input type="hidden" name="TechnicianID" value="<?= $editId ?>">
  <h3>Edit <?= e($t['Name'] ?? '') ?></h3>
  <label style="font-weight:normal"><input type="checkbox" name="IsAvailable" value="1" style="width:auto" <?= !empty($t['IsAvailable']) ? 'checked' : '' ?>> Available (untick when on leave)</label>
  <label>Specialisms <span class="req">*</span></label>
  <?= $skillBoxes($skillsOf[$editId] ?? []) ?>
  <div class="actions"><a class="btn" href="<?= e(url('registers/technicians.php')) ?>">Cancel</a>
    <button class="btn btn-primary" name="action" value="save">Save</button></div>
</form>
<?php elseif ($canEdit && input('new') === '1'): ?>
<form method="post" class="card">
  <?= Csrf::field() ?>
  <h3>Add technician</h3>
  <label for="EmployeeID">Employee <span class="req">*</span></label>
  <select id="EmployeeID" name="EmployeeID" required><?= options(Lookup::currentEmployees(), null, '- choose employee -') ?></select>
  <label style="font-weight:normal"><input type="checkbox" name="IsAvailable" value="1" style="width:auto" checked> Available</label>
  <label>Specialisms <span class="req">*</span></label>
  <?= $skillBoxes([]) ?>
  <div class="actions"><a class="btn" href="<?= e(url('registers/technicians.php')) ?>">Cancel</a>
    <button class="btn btn-primary" name="action" value="add">Add technician</button></div>
</form>
<?php endif; ?>

<section class="card">
  <div class="actions" style="margin-top:0;justify-content:space-between">
    <h3>Technicians</h3>
    <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= e(url('registers/technicians.php', ['new' => 1])) ?>">Add technician</a><?php endif; ?>
  </div>
  <table class="data">
    <tr><th>Technician</th><th>Office</th><th>Specialisms</th><th class="num">Open jobs</th><th>Available</th><?= $canEdit ? '<th></th>' : '' ?></tr>
    <?php foreach ($techs as $t): ?>
    <tr><td><?= e($t['Name']) ?> <span class="hint">(<?= e($t['EmployeeID']) ?>)</span></td><td><?= e($t['OfficeName']) ?></td>
      <td><?= e(implode(', ', array_map(fn($id) => $types[$id], $skillsOf[$t['TechnicianID']] ?? []))) ?></td>
      <td class="num"><?= (int)$t['OpenJobs'] ?></td><td><?= badge($t['IsAvailable'] ? 'Yes' : 'On leave') ?></td>
      <?php if ($canEdit): ?><td><a class="btn btn-small" href="<?= e(url('registers/technicians.php', ['edit' => $t['TechnicianID']])) ?>">Edit</a></td><?php endif; ?></tr>
    <?php endforeach; ?>
  </table>
</section>
<?php render('footer'); ?>
