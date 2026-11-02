<?php
/**
 * Log New Call (Design Document Figure 6) - Helpdesk Operators only.
 * Caller and equipment are checked against the registers (UR-02, UR-03),
 * licence status is shown for software (UR-04), and the problem plus its
 * first call are saved in one transaction by sp_LogNewProblem (UR-05).
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('call.log');

$errors = [];
$f = [
    'CallerID' => '', 'SerialNumber' => '', 'OSSoftwareID' => '', 'SoftwareID' => '',
    'ProblemTypeID' => '', 'Priority' => 'Medium', 'UrgencyLevel' => 'Normal',
    'CallReason' => '', 'Description' => '',
];

if (is_post()) {
    Csrf::check();
    foreach ($f as $k => $_) {
        $f[$k] = input($k);
    }

    $employees = Lookup::currentEmployees();
    $hardware  = Database::row('SELECT HardwareID FROM Hardware WHERE SerialNumber = ?', [$f['SerialNumber']]);

    $v = new Validator($f);
    $v->required('CallerID', 'Please choose the caller.')
      ->rule($f['CallerID'] === '' || isset($employees[$f['CallerID']]), 'CallerID', 'Caller not found or no longer employed.')
      ->required('SerialNumber', 'Please enter the computer serial number.')
      ->rule($f['SerialNumber'] === '' || $hardware !== null, 'SerialNumber', 'Serial number not found in equipment register.')
      ->required('ProblemTypeID', 'Please choose a problem type.')
      ->inList('ProblemTypeID', array_keys(Lookup::problemTypes()), 'Please choose a problem type.')
      ->inList('OSSoftwareID', array_keys(Lookup::software(true)), 'Choose an operating system from the list.')
      ->inList('SoftwareID', array_keys(Lookup::software(false)), 'Choose software from the list.')
      ->required('Priority', 'Please choose a priority.')
      ->inList('Priority', Lookup::PRIORITIES, 'Invalid priority.')
      ->inList('UrgencyLevel', Lookup::URGENCY, 'Invalid urgency.')
      ->required('CallReason', 'Please enter the reason for the call.')
      ->minLength('CallReason', 3, 'Please enter the reason for the call.')
      ->maxLength('CallReason', 255, 'Reason must be 255 characters or fewer.')
      ->required('Description', 'Please describe the problem (min. 10 characters).')
      ->minLength('Description', 10, 'Please describe the problem (min. 10 characters).');

    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        try {
            $st = Database::current()->prepare('CALL sp_LogNewProblem(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @pno)');
            $st->execute([
                $f['CallerID'], Auth::id(), (int)$f['ProblemTypeID'], (int)$hardware['HardwareID'],
                $f['OSSoftwareID'] !== '' ? (int)$f['OSSoftwareID'] : null,
                $f['SoftwareID'] !== '' ? (int)$f['SoftwareID'] : null,
                $f['Description'], $f['Priority'], $f['CallReason'], $f['UrgencyLevel'],
            ]);
            $problemNumber = (int)$st->fetch()['ProblemNumber'];
            $st->closeCursor();

            flash('success', 'Problem ' . problem_ref($problemNumber)
                . ' logged. Please give this problem number to the caller. Now assign a technician.');
            redirect('calls/assign.php', ['problem' => $problemNumber]);
        } catch (PDOException $e) {
            $errors['_form'] = DbError::message($e);
        }
    }
}

// Next problem number, shown before saving as in the wireframe (Figure 6).
// It is provisional: if another operator saves first, the number given
// on saving is the one that counts and is shown in the success message.
Database::exec('SET SESSION information_schema_stats_expiry = 0');
$nextNumber = Database::value("SELECT AUTO_INCREMENT FROM information_schema.TABLES
                                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Problem'");

$cls = fn(string $k) => isset($errors[$k]) ? ' class="has-error"' : '';
render('header', ['title' => 'Log New Call']);
?>
<form method="post" data-confirm="Save this call? Please check the caller and equipment details are correct.">
  <?= Csrf::field() ?>
  <?php if (isset($errors['_form'])): ?><div class="flash flash-error"><?= e($errors['_form']) ?></div><?php endif; ?>

  <section class="card">
    <div class="grid grid-2">
      <div><strong>Problem No. (auto):</strong>
        <span class="problem-no"><?= $nextNumber ? e(problem_ref($nextNumber)) : 'given when saved' ?></span>
        <span class="hint">confirmed when saved</span></div>
      <div><strong>Call date/time (auto):</strong> <?= e(date('d/m/Y H:i')) ?></div>
    </div>
  </section>

  <div class="grid grid-2">
    <section class="card">
      <h3>1. Caller</h3>
      <div<?= $cls('CallerID') ?>>
        <label for="CallerID">Caller name (personnel register) <span class="req">*</span></label>
        <select id="CallerID" name="CallerID" required data-lookup="<?= e(url('api/employee.php')) ?>">
          <?= options(Lookup::currentEmployees(), $f['CallerID'], '- search / choose caller -') ?>
        </select>
        <?= field_error($errors, 'CallerID') ?>
      </div>
      <div class="grid grid-2">
        <div><label for="c_job">Job title</label><input id="c_job" readonly></div>
        <div><label for="c_dept">Department</label><input id="c_dept" readonly></div>
      </div>
      <label for="c_office">Office</label><input id="c_office" readonly>
    </section>

    <section class="card">
      <h3>2. Equipment &amp; software</h3>
      <div<?= $cls('SerialNumber') ?>>
        <label for="SerialNumber">Computer serial number <span class="req">*</span>
          <span id="warranty_badge" class="badge badge-info">enter serial</span></label>
        <input id="SerialNumber" name="SerialNumber" value="<?= e($f['SerialNumber']) ?>" required maxlength="30"
               pattern="[A-Za-z0-9\-]+" list="serials" data-lookup="<?= e(url('api/hardware.php')) ?>">
        <datalist id="serials">
          <?php foreach (Database::rows('SELECT SerialNumber FROM Hardware ORDER BY SerialNumber') as $h): ?>
            <option value="<?= e($h['SerialNumber']) ?>">
          <?php endforeach; ?>
        </datalist>
        <?= field_error($errors, 'SerialNumber') ?>
      </div>
      <div class="grid grid-2">
        <div><label for="h_type">Type / Make</label><input id="h_type" readonly></div>
        <div><label for="h_model">Model</label><input id="h_model" readonly></div>
      </div>
      <label for="h_installed">Installed software (licence status)</label><input id="h_installed" readonly>
      <div class="grid grid-2">
        <div<?= $cls('OSSoftwareID') ?>>
          <label for="OSSoftwareID">Operating system</label>
          <select id="OSSoftwareID" name="OSSoftwareID"><?= options(Lookup::software(true), $f['OSSoftwareID'], '- not relevant -') ?></select>
          <?= field_error($errors, 'OSSoftwareID') ?>
        </div>
        <div<?= $cls('SoftwareID') ?>>
          <label for="SoftwareID">Software</label>
          <select id="SoftwareID" name="SoftwareID"><?= options(Lookup::software(false), $f['SoftwareID'], '- not relevant -') ?></select>
          <?= field_error($errors, 'SoftwareID') ?>
        </div>
      </div>
    </section>
  </div>

  <section class="card">
    <h3>3. Problem details</h3>
    <div class="grid grid-3">
      <div<?= $cls('ProblemTypeID') ?>>
        <label for="ProblemTypeID">Problem type <span class="req">*</span></label>
        <select id="ProblemTypeID" name="ProblemTypeID" required><?= options(Lookup::problemTypes(), $f['ProblemTypeID'], '- choose -') ?></select>
        <?= field_error($errors, 'ProblemTypeID') ?>
      </div>
      <div<?= $cls('Priority') ?>>
        <label for="Priority">Priority <span class="req">*</span></label>
        <select id="Priority" name="Priority" required><?= options(Lookup::same(Lookup::PRIORITIES), $f['Priority']) ?></select>
        <?= field_error($errors, 'Priority') ?>
      </div>
      <div>
        <label for="UrgencyLevel">Urgency of caller</label>
        <select id="UrgencyLevel" name="UrgencyLevel"><?= options(Lookup::same(Lookup::URGENCY), $f['UrgencyLevel']) ?></select>
      </div>
    </div>
    <div<?= $cls('CallReason') ?>>
      <label for="CallReason">Reason for call <span class="req">*</span></label>
      <input id="CallReason" name="CallReason" value="<?= e($f['CallReason']) ?>" required minlength="3" maxlength="255">
      <?= field_error($errors, 'CallReason') ?>
    </div>
    <div<?= $cls('Description') ?>>
      <label for="Description">Description / notes <span class="req">*</span></label>
      <textarea id="Description" name="Description" required minlength="10"><?= e($f['Description']) ?></textarea>
      <?= field_error($errors, 'Description') ?>
    </div>
    <p class="hint">* required field. Name and serial number are checked against the registers before saving.</p>
  </section>

  <div class="actions">
    <a class="btn" href="<?= e(url('main.php')) ?>">Cancel</a>
    <a class="btn" href="<?= e(url('calls/new.php')) ?>">Clear</a>
    <button class="btn btn-primary">Save &amp; Assign</button>
  </div>
</form>
<?php render('footer'); ?>
