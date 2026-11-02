<?php
/**
 * Search Problem / Problem detail: full problem record and its call history.
 * Available to every role (read only).
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('call.view');

$search = input('problem');
$problemNumber = parse_problem_ref($search);
$p = $problemNumber ? Database::row('SELECT * FROM vw_ProblemDetail WHERE ProblemNumber = ?', [$problemNumber]) : null;
$calls = $p ? Database::rows(
    'SELECT CallDateTime, CallType, CallReason, UrgencyLevel, Notes,
            CallerName AS Caller, OperatorName AS Operator
       FROM vw_CallDetail
      WHERE ProblemNumber = ? ORDER BY CallDateTime, CallID', [$problemNumber]) : [];

render('header', ['title' => 'Problem Details']);
?>
<section class="card">
  <form class="toolbar" method="get">
    <div><label for="problem">Problem No.</label>
      <input id="problem" name="problem" value="<?= e($search) ?>" placeholder="P-000128" required></div>
    <button class="btn btn-primary">Search</button>
  </form>
  <?php if ($search !== '' && !$p): ?><div class="flash flash-error">Problem number <?= e($search) ?> does not exist.</div><?php endif; ?>
</section>

<?php if ($p): ?>
<section class="card">
  <h2><?= e($p['ProblemRef']) ?> <?= badge($p['Status']) ?> <?= badge($p['Priority']) ?></h2>
  <div class="grid grid-2">
    <table class="data">
      <tr><th>Problem type</th><td><?= e($p['ProblemType']) ?> (<?= e($p['FaultCategory']) ?>)</td></tr>
      <tr><th>Reported by</th><td><?= e($p['ReporterName']) ?> (<?= e($p['ReporterID']) ?>)</td></tr>
      <tr><th>Office</th><td><?= e($p['OfficeName']) ?> - <?= e($p['OfficeSpecialisation']) ?></td></tr>
      <tr><th>Logged</th><td><?= e(fmt_datetime($p['DateTimeLogged'])) ?></td></tr>
      <tr><th>Description</th><td><?= e($p['Description']) ?></td></tr>
    </table>
    <table class="data">
      <tr><th>Equipment</th><td><?= e($p['SerialNumber'] ? $p['SerialNumber'] . ' - ' . $p['EquipmentName'] : '—') ?></td></tr>
      <tr><th>Operating system</th><td><?= e($p['OperatingSystem'] ?? '—') ?></td></tr>
      <tr><th>Software</th><td><?= e($p['Application'] ?? '—') ?></td></tr>
      <tr><th>Technician</th><td><?= e($p['TechnicianName'] ?? 'Not assigned') ?>
          <?= $p['DateTimeAssigned'] ? '(assigned ' . e(fmt_datetime($p['DateTimeAssigned'])) . ')' : '' ?></td></tr>
      <tr><th>Resolved</th><td><?= $p['DateTimeResolved']
          ? e(fmt_datetime($p['DateTimeResolved']) . ' - ' . fmt_hours($p['TimeTakenHours']) . ' h - ' . $p['ResolutionNotes'])
          : '—' ?></td></tr>
    </table>
  </div>
  <div class="actions">
    <?php if ($p['Status'] === 'Open' && Auth::can('call.followup')): ?>
      <a class="btn" href="<?= e(url('calls/followup.php', ['problem' => $p['ProblemNumber']])) ?>">Follow-up Call</a>
    <?php endif; ?>
    <?php if ($p['Status'] === 'Open' && Auth::can('call.assign')): ?>
      <a class="btn btn-primary" href="<?= e(url('calls/assign.php', ['problem' => $p['ProblemNumber']])) ?>"><?= $p['TechnicianID'] ? 'Re-assign' : 'Assign' ?> Technician</a>
    <?php endif; ?>
    <?php if ($p['Status'] === 'Open' && Auth::can('call.resolve') && $p['TechnicianID']
              && (Auth::role() !== 'Technician' || (int)$p['TechnicianID'] === Auth::technicianId())): ?>
      <a class="btn btn-success" href="<?= e(url('calls/resolve.php', ['problem' => $p['ProblemNumber']])) ?>">Resolve</a>
    <?php endif; ?>
  </div>
</section>

<section class="card">
  <h3>Call history (<?= count($calls) ?>)</h3>
  <table class="data">
    <tr><th>Date/time</th><th>Type</th><th>Caller</th><th>Operator</th><th>Reason</th><th>Urgency</th><th>Notes</th></tr>
    <?php foreach ($calls as $c): ?>
    <tr><td><?= e(fmt_datetime($c['CallDateTime'])) ?></td><td><?= e($c['CallType']) ?></td>
        <td><?= e($c['Caller']) ?></td><td><?= e($c['Operator']) ?></td><td><?= e($c['CallReason']) ?></td>
        <td><?= badge($c['UrgencyLevel']) ?></td><td><?= e($c['Notes']) ?></td></tr>
    <?php endforeach; ?>
  </table>
</section>
<?php endif; ?>
<?php render('footer'); ?>
