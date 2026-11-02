<?php
/**
 * Follow-up Call (Design Document Figure 7) - Helpdesk Operators only.
 * The caller quotes the problem number; a new call is recorded against it
 * with a reason that is always required (UR-06).
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('call.followup');

$search = input('problem');
$problemNumber = parse_problem_ref($search);
$problem = $problemNumber
    ? Database::row('SELECT * FROM vw_ProblemDetail WHERE ProblemNumber = ?', [$problemNumber])
    : null;

$errors = [];
$f = ['CallerID' => $problem['ReporterID'] ?? '', 'UrgencyLevel' => 'Normal', 'CallReason' => '', 'Notes' => ''];

if (is_post() && $problem) {
    Csrf::check();
    foreach ($f as $k => $_) {
        $f[$k] = input($k);
    }
    $v = new Validator($f);
    $v->required('CallerID', 'Please choose the caller.')
      ->rule($f['CallerID'] === '' || isset(Lookup::currentEmployees()[$f['CallerID']]), 'CallerID', 'Caller not found or no longer employed.')
      ->inList('UrgencyLevel', Lookup::URGENCY, 'Invalid urgency.')
      ->required('CallReason', 'Please enter the reason for the call.')
      ->minLength('CallReason', 3, 'Please enter the reason for the call.')
      ->maxLength('CallReason', 255, 'Reason must be 255 characters or fewer.');
    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        try {
            Database::call('sp_LogFollowUpCall', [$problemNumber, $f['CallerID'], Auth::id(),
                $f['CallReason'], $f['UrgencyLevel'], $f['Notes'] !== '' ? $f['Notes'] : null]);
            flash('success', 'Follow-up call recorded against ' . problem_ref($problemNumber) . '.');
            redirect('calls/view.php', ['problem' => $problemNumber]);
        } catch (PDOException $e) {
            $errors['_form'] = DbError::message($e);
        }
    }
}

$first = $problem ? Database::row(
    'SELECT CallReason, CallDateTime, OperatorName AS Operator
       FROM vw_CallDetail
      WHERE ProblemNumber = ? ORDER BY CallDateTime, CallID LIMIT 1', [$problemNumber]) : null;

$cls = fn(string $k) => isset($errors[$k]) ? ' class="has-error"' : '';
render('header', ['title' => 'Follow-up Call']);
?>
<section class="card">
  <form class="toolbar" method="get">
    <div><label for="problem">Problem number quoted by caller <span class="req">*</span></label>
      <input id="problem" name="problem" value="<?= e($search) ?>" placeholder="P-000128" required></div>
    <button class="btn btn-primary">Find</button>
  </form>
  <?php if ($search !== '' && !$problem): ?>
    <div class="flash flash-error">Problem number <?= e($search) ?> does not exist.</div>
  <?php endif; ?>
</section>

<?php if ($problem): ?>
<section class="card">
  <h3>Problem summary (read-only)</h3>
  <table class="data">
    <tr><th>Problem No.</th><th>Caller</th><th>Equipment</th><th>Technician</th><th>Status</th><th>Calls</th></tr>
    <tr>
      <td><?= e($problem['ProblemRef']) ?></td><td><?= e($problem['ReporterName']) ?></td>
      <td><?= e($problem['EquipmentName'] ?? '—') ?></td><td><?= e($problem['TechnicianName'] ?? 'Not assigned') ?></td>
      <td><?= badge($problem['Status']) ?></td><td><?= (int)$problem['CallCount'] ?> previous</td>
    </tr>
  </table>
  <?php if ($first): ?>
    <p>Original reason: <?= e($first['CallReason']) ?><br>
       <span class="hint">Logged <?= e(fmt_datetime($first['CallDateTime'])) ?> by <?= e($first['Operator']) ?></span></p>
  <?php endif; ?>
</section>

<form method="post" class="card">
  <?= Csrf::field() ?>
  <input type="hidden" name="problem" value="<?= e($problemNumber) ?>">
  <h3>This call</h3>
  <?php if (isset($errors['_form'])): ?><div class="flash flash-error"><?= e($errors['_form']) ?></div><?php endif; ?>
  <div class="grid grid-2">
    <div<?= $cls('CallerID') ?>>
      <label for="CallerID">Caller name <span class="req">*</span></label>
      <select id="CallerID" name="CallerID" required><?= options(Lookup::currentEmployees(), $f['CallerID'], '- choose caller -') ?></select>
      <?= field_error($errors, 'CallerID') ?>
    </div>
    <div>
      <label for="UrgencyLevel">Urgency of caller</label>
      <select id="UrgencyLevel" name="UrgencyLevel"><?= options(Lookup::same(Lookup::URGENCY), $f['UrgencyLevel']) ?></select>
    </div>
  </div>
  <div<?= $cls('CallReason') ?>>
    <label for="CallReason">Reason for call (always required) <span class="req">*</span></label>
    <input id="CallReason" name="CallReason" value="<?= e($f['CallReason']) ?>" required minlength="3" maxlength="255"
           placeholder="e.g. Chasing progress - still cannot open file">
    <?= field_error($errors, 'CallReason') ?>
  </div>
  <label for="Notes">Notes</label>
  <textarea id="Notes" name="Notes"><?= e($f['Notes']) ?></textarea>
  <div class="actions"><button class="btn btn-primary">Save Follow-up</button></div>
</form>
<?php endif; ?>
<?php render('footer'); ?>
