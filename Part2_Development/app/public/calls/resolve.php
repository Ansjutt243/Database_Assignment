<?php
/**
 * Resolve Problem (Design Document Figure 9) - Operators, and Technicians
 * for their own jobs. Date/time resolved, hours and notes are required
 * (UR-09); saving sets Status = Closed through sp_ResolveProblem, which
 * also checks that a technician only closes their own jobs.
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('call.resolve');

$isTech = Auth::role() === 'Technician';
$search = input('problem');
$problemNumber = parse_problem_ref($search);
$problem = $problemNumber
    ? Database::row('SELECT * FROM vw_ProblemDetail WHERE ProblemNumber = ?', [$problemNumber])
    : null;

$errors = [];
// seconds included: a job closed in the same minute it was logged must still be "after" it
$f = ['DateTimeResolved' => date('Y-m-d\TH:i:s'), 'TimeTakenHours' => '', 'ResolutionNotes' => ''];

if (is_post() && $problem) {
    Csrf::check();
    foreach ($f as $k => $_) {
        $f[$k] = input($k);
    }
    $resolved = Validator::toDateTime($f['DateTimeResolved']);
    $v = new Validator($f);
    $v->required('DateTimeResolved', 'Please enter the date and time resolved.')
      ->dateTime('DateTimeResolved', 'Enter a valid date and time.')
      ->notFuture('DateTimeResolved', 'Resolved time cannot be in the future.')
      ->rule($resolved === null || $resolved > $problem['DateTimeLogged'], 'DateTimeResolved', 'Resolved time must be after the logged time.')
      ->required('TimeTakenHours', 'Please enter the time taken in hours.')
      ->number('TimeTakenHours', 0.25, 500, 'Time taken must be between 0.25 and 500 hours.')
      ->required('ResolutionNotes', 'Please describe how the problem was resolved.')
      ->rule(!$isTech || (int)$problem['TechnicianID'] === Auth::technicianId(), '_form', 'Technicians can only resolve their own jobs.');
    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        try {
            Database::call('sp_ResolveProblem', [$problemNumber, Auth::id(), $resolved,
                $f['ResolutionNotes'], $f['TimeTakenHours']]);
            flash('success', problem_ref($problemNumber) . ' has been resolved and closed.');
            redirect('calls/view.php', ['problem' => $problemNumber]);
        } catch (PDOException $e) {
            $errors['_form'] = DbError::message($e);
        }
    }
}

// Technicians get a quick list of their own open jobs
$myJobs = $isTech ? Database::rows(
    "SELECT ProblemNumber, ProblemRef, DateTimeLogged, ProblemType, Priority, ReporterName, OfficeName
       FROM vw_ProblemDetail WHERE Status = 'Open' AND TechnicianID = ?
      ORDER BY DateTimeLogged", [Auth::technicianId()]) : [];

$cls = fn(string $k) => isset($errors[$k]) ? ' class="has-error"' : '';
render('header', ['title' => 'Resolve Problem']);
?>
<section class="card">
  <form class="toolbar" method="get">
    <div><label for="problem">Problem No. <span class="req">*</span></label>
      <input id="problem" name="problem" value="<?= e($search) ?>" placeholder="P-000128" required></div>
    <button class="btn btn-primary">Find</button>
  </form>
  <?php if ($search !== '' && !$problem): ?>
    <div class="flash flash-error">Problem number <?= e($search) ?> does not exist.</div>
  <?php endif; ?>

  <?php if ($isTech && !$problem): ?>
    <h3>My open jobs</h3>
    <?php if (!$myJobs): ?><p>You have no open jobs.</p><?php else: ?>
    <table class="data">
      <tr><th>Problem No.</th><th>Logged</th><th>Type</th><th>Priority</th><th>Reported by</th><th>Office</th><th></th></tr>
      <?php foreach ($myJobs as $j): ?>
      <tr><td><?= e($j['ProblemRef']) ?></td><td><?= e(fmt_datetime($j['DateTimeLogged'])) ?></td>
          <td><?= e($j['ProblemType']) ?></td><td><?= badge($j['Priority']) ?></td>
          <td><?= e($j['ReporterName']) ?></td><td><?= e($j['OfficeName']) ?></td>
          <td><a class="btn btn-small btn-primary" href="<?= e(url('calls/resolve.php', ['problem' => $j['ProblemNumber']])) ?>">Resolve</a></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php if ($problem): ?>
<section class="card">
  <div class="grid grid-3">
    <div><label>Problem</label><input readonly value="<?= e($problem['ProblemRef'] . ' - ' . $problem['ProblemType']) ?>"></div>
    <div><label>Assigned technician</label><input readonly value="<?= e($problem['TechnicianName'] ?? 'Not assigned') ?>"></div>
    <div><label>Logged</label><input readonly value="<?= e(fmt_datetime($problem['DateTimeLogged'])) ?>"></div>
  </div>
  <p><?= e($problem['Description']) ?></p>
</section>

<?php if ($problem['Status'] === 'Closed'): ?>
  <div class="flash flash-info">This problem was already closed on <?= e(fmt_datetime($problem['DateTimeResolved'])) ?>
    (<?= e(fmt_hours($problem['TimeTakenHours'])) ?> hours): <?= e($problem['ResolutionNotes']) ?></div>
<?php else: ?>
<form method="post" class="card" data-confirm="Close this problem?">
  <?= Csrf::field() ?>
  <input type="hidden" name="problem" value="<?= e($problemNumber) ?>">
  <h3>Resolution</h3>
  <?php if (isset($errors['_form'])): ?><div class="flash flash-error"><?= e($errors['_form']) ?></div><?php endif; ?>
  <div class="grid grid-3">
    <div<?= $cls('DateTimeResolved') ?>>
      <label for="DateTimeResolved">Date/time resolved <span class="req">*</span></label>
      <input id="DateTimeResolved" name="DateTimeResolved" type="datetime-local" value="<?= e($f['DateTimeResolved']) ?>"
             max="<?= e(date('Y-m-d\TH:i:s')) ?>" step="1" required>
      <?= field_error($errors, 'DateTimeResolved') ?>
    </div>
    <div<?= $cls('TimeTakenHours') ?>>
      <label for="TimeTakenHours">Time taken (hours) <span class="req">*</span></label>
      <input id="TimeTakenHours" name="TimeTakenHours" type="number" step="0.01" min="0.25" max="500"
             value="<?= e($f['TimeTakenHours']) ?>" required placeholder="e.g. 1.5 = 1 h 30 min">
      <?= field_error($errors, 'TimeTakenHours') ?>
    </div>
    <div><label>Resolved by</label><input readonly value="<?= e(Auth::user()['name']) ?>"></div>
  </div>
  <div<?= $cls('ResolutionNotes') ?>>
    <label for="ResolutionNotes">How was the problem resolved? <span class="req">*</span></label>
    <textarea id="ResolutionNotes" name="ResolutionNotes" required><?= e($f['ResolutionNotes']) ?></textarea>
    <?= field_error($errors, 'ResolutionNotes') ?>
  </div>
  <p class="note">Resolved date/time must be after the logged date/time; time taken must be between 0.25 and 500 hours.
     Saving sets Status = Closed.</p>
  <div class="actions">
    <a class="btn" href="<?= e(url('main.php')) ?>">Cancel</a>
    <button class="btn btn-success">Mark as Resolved</button>
  </div>
</form>
<?php endif; endif; ?>
<?php render('footer'); ?>
