<?php
/**
 * User Accounts - System Administrator only (access matrix, Table 40).
 * Create accounts, change role, unlock locked accounts, reset passwords.
 * Passwords are typed twice (verification, 8.4) and stored only as bcrypt
 * hashes made by password_hash() (NFR-02).
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('users.manage');

$errors = [];
$f = ['UserID' => '', 'Username' => '', 'EmployeeID' => '', 'Role' => '', 'Password' => '', 'Password2' => ''];

/** Password policy: at least 8 characters with a letter and a number. */
$checkPassword = function (Validator $v, array $f, bool $required): Validator {
    if ($required) {
        $v->required('Password', 'Please enter a password.');
    }
    return $v->minLength('Password', 8, 'Password must be at least 8 characters.')
             ->pattern('Password', '/^(?=.*[A-Za-z])(?=.*\d).+$/', 'Password needs at least one letter and one number.')
             ->rule($f['Password'] === $f['Password2'], 'Password2', 'The two passwords do not match.');
};

if (is_post()) {
    Csrf::check();
    $action = input('action');
    $id = input('UserID');

    if ($action === 'unlock' && ctype_digit($id)) {
        Database::exec('UPDATE AppUser SET IsActive = TRUE, FailedLogins = 0 WHERE UserID = ?', [(int)$id]);
        flash('success', 'Account unlocked.');
        redirect('users/index.php');
    }
    if ($action === 'lock' && ctype_digit($id) && (int)$id !== Auth::id()) {
        Database::exec('UPDATE AppUser SET IsActive = FALSE WHERE UserID = ?', [(int)$id]);
        flash('success', 'Account disabled.');
        redirect('users/index.php');
    }

    foreach ($f as $k => $_) {
        $f[$k] = $k === 'Password' || $k === 'Password2' ? (string)($_POST[$k] ?? '') : input($k);
    }
    $isNew = !ctype_digit($f['UserID']);
    $v = new Validator($f);
    $v->required('Role', 'Please choose a role.')->inList('Role', Auth::ROLES, 'Choose a role from the list.');
    if ($isNew) {
        $v->required('Username', 'Please enter a username.')
          ->pattern('Username', '/^[A-Za-z0-9._]{4,30}$/', 'Username must be 4-30 letters, digits, dots or underscores.')
          ->required('EmployeeID', 'Please choose the employee.');
    }
    $checkPassword($v, $f, $isNew);

    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        try {
            if ($isNew) {
                Database::exec('INSERT INTO AppUser (Username, PasswordHash, Role, EmployeeID) VALUES (?, ?, ?, ?)',
                    [$f['Username'], password_hash($f['Password'], PASSWORD_BCRYPT), $f['Role'], $f['EmployeeID']]);
            } else {
                Database::exec('UPDATE AppUser SET Role = ? WHERE UserID = ?', [$f['Role'], (int)$f['UserID']]);
                if ($f['Password'] !== '') {
                    Database::exec('UPDATE AppUser SET PasswordHash = ?, FailedLogins = 0 WHERE UserID = ?',
                        [password_hash($f['Password'], PASSWORD_BCRYPT), (int)$f['UserID']]);
                }
            }
            flash('success', 'Account saved.');
            redirect('users/index.php');
        } catch (PDOException $e) {
            $errors['_form'] = DbError::message($e);
        }
    }
} elseif (ctype_digit(input('edit'))) {
    $row = Database::row('SELECT UserID, Username, EmployeeID, Role FROM AppUser WHERE UserID = ?', [(int)input('edit')]);
    if ($row) {
        $f = array_merge($f, $row);
    }
}

$users = Database::rows("SELECT u.UserID, u.Username, u.Role, u.IsActive, u.FailedLogins, u.EmployeeID,
                                CONCAT(e.FirstName, ' ', e.LastName) AS Name, e.EndDate
                           FROM AppUser u JOIN Employee e ON e.EmployeeID = u.EmployeeID
                          ORDER BY FIELD(u.Role, 'Operator', 'Technician', 'Manager', 'Admin'), u.Username");
$isNew = !ctype_digit((string)$f['UserID']);
$showForm = input('new') === '1' || input('edit') !== '' || $errors;

render('header', ['title' => 'User Accounts']);
?>
<?php if ($showForm): ?>
<form method="post" class="card" autocomplete="off">
  <?= Csrf::field() ?><input type="hidden" name="UserID" value="<?= e($f['UserID']) ?>">
  <h3><?= $isNew ? 'Create account' : 'Edit account ' . e($f['Username']) ?></h3>
  <?php if (isset($errors['_form'])): ?><div class="flash flash-error"><?= e($errors['_form']) ?></div><?php endif; ?>
  <div class="grid grid-3">
    <?= form_field('Username', 'Username', $f['Username'], $errors, 'text', ['required' => $isNew, 'maxlength' => 30, 'readonly' => !$isNew]) ?>
    <?php if ($isNew): ?>
      <?= form_field('EmployeeID', 'Employee', $f['EmployeeID'], $errors, 'select', ['required' => true], Lookup::currentEmployees()) ?>
    <?php else: ?>
      <div><label>Employee</label><input readonly value="<?= e(Lookup::allEmployees()[$f['EmployeeID']] ?? '') ?>"></div>
    <?php endif; ?>
    <?= form_field('Role', 'Role', $f['Role'], $errors, 'select', ['required' => true], Auth::ROLE_LABELS) ?>
    <?= form_field('Password', $isNew ? 'Password' : 'New password (blank = keep)', '', $errors, 'password', ['required' => $isNew, 'minlength' => 8, 'autocomplete' => 'new-password']) ?>
    <?= form_field('Password2', 'Type the password again', '', $errors, 'password', ['required' => $isNew, 'autocomplete' => 'new-password']) ?>
  </div>
  <p class="hint">At least 8 characters with a letter and a number. Passwords are stored only as bcrypt hashes.</p>
  <div class="actions"><a class="btn" href="<?= e(url('users/index.php')) ?>">Cancel</a><button class="btn btn-primary">Save</button></div>
</form>
<?php endif; ?>

<section class="card">
  <div class="actions" style="margin-top:0;justify-content:space-between">
    <h3>Accounts</h3><a class="btn btn-primary" href="<?= e(url('users/index.php', ['new' => 1])) ?>">Create account</a>
  </div>
  <table class="data">
    <tr><th>Username</th><th>Name</th><th>Role</th><th class="num">Failed log-ins</th><th>Status</th><th></th></tr>
    <?php foreach ($users as $u): ?>
    <tr><td><?= e($u['Username']) ?></td><td><?= e($u['Name']) ?> <span class="hint">(<?= e($u['EmployeeID']) ?>)</span></td>
      <td><?= e(Auth::ROLE_LABELS[$u['Role']]) ?></td><td class="num"><?= (int)$u['FailedLogins'] ?></td>
      <td><?= badge($u['IsActive'] ? 'Active' : 'Locked') ?></td>
      <td>
        <a class="btn btn-small" href="<?= e(url('users/index.php', ['edit' => $u['UserID']])) ?>">Edit</a>
        <form method="post" style="display:inline"><?= Csrf::field() ?><input type="hidden" name="UserID" value="<?= (int)$u['UserID'] ?>">
          <?php if (!$u['IsActive']): ?>
            <button class="btn btn-small btn-success" name="action" value="unlock">Unlock</button>
          <?php elseif ((int)$u['UserID'] !== Auth::id()): ?>
            <button class="btn btn-small btn-danger" name="action" value="lock">Disable</button>
          <?php endif; ?>
        </form>
      </td></tr>
    <?php endforeach; ?>
  </table>
</section>
<?php render('footer'); ?>
