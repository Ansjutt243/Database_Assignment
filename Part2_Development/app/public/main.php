<?php
/**
 * Main Control Page (Design Document Figure 5).
 * Shows key figures and only the menu groups allowed for the user's role.
 * "Back Up Database Now" is here for every user, as the brief requires.
 */
require dirname(__DIR__) . '/src/bootstrap.php';
Auth::requireLogin();

$role = Auth::role();
$openSql = 'SELECT COUNT(*) FROM Problem WHERE Status = \'Open\'';
$openParams = [];
if ($role === 'Technician') {                       // technicians see their own figures
    $openSql .= ' AND TechnicianID = ?';
    $openParams[] = Auth::technicianId();
}
$open = (int)Database::value($openSql, $openParams);
$closedThisMonth = (int)Database::value(
    "SELECT COUNT(*) FROM Problem
      WHERE Status = 'Closed' AND DateTimeResolved >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')");
$lastBackup = Maintenance::lastBackup();

render('header', ['title' => 'Main Control Page']);
?>
<div class="grid grid-3">
  <section class="card stat">
    <div class="label"><?= $role === 'Technician' ? 'My open jobs' : 'Open problems' ?></div>
    <div class="value"><?= $open ?></div>
  </section>
  <section class="card stat">
    <div class="label">Closed this month</div>
    <div class="value green"><?= $closedThisMonth ?></div>
  </section>
  <section class="card stat">
    <div class="label">Last backup</div>
    <div class="value small"><?= $lastBackup ? e(fmt_datetime($lastBackup)) : 'None yet' ?></div>
  </section>
</div>

<div class="grid grid-2">
  <section class="card">
    <h3>Helpdesk jobs</h3>
    <div class="btn-grid">
      <?php if (Auth::can('call.log')): ?>
        <a class="btn btn-primary" href="<?= e(url('calls/new.php')) ?>">Log New Call</a>
        <a class="btn btn-primary" href="<?= e(url('calls/followup.php')) ?>">Follow-up Call</a>
        <a class="btn btn-primary" href="<?= e(url('calls/assign.php')) ?>">Assign Technician</a>
      <?php endif; ?>
      <?php if (Auth::can('call.resolve')): ?>
        <a class="btn btn-primary" href="<?= e(url('calls/resolve.php')) ?>">Resolve Problem</a>
      <?php endif; ?>
      <a class="btn" href="<?= e(url('calls/open.php')) ?>"><?= $role === 'Technician' ? 'My Open Jobs' : 'Open Problems List' ?></a>
      <a class="btn" href="<?= e(url('calls/view.php')) ?>">Search Problem</a>
    </div>
    <?php if (!Auth::can('call.log')): ?>
      <p class="hint">Logging jobs is for Helpdesk Operators only<?= $role === 'Manager' ? ' (office managers cannot log jobs)' : '' ?>.</p>
    <?php endif; ?>
  </section>

  <section class="card">
    <h3>Registers &amp; Reports</h3>
    <div class="btn-grid">
      <a class="btn" href="<?= e(url('registers/employees.php')) ?>">Employees</a>
      <a class="btn" href="<?= e(url('registers/offices.php')) ?>">Offices</a>
      <a class="btn" href="<?= e(url('registers/hardware.php')) ?>">Hardware</a>
      <a class="btn" href="<?= e(url('registers/software.php')) ?>">Software &amp; Licences</a>
      <a class="btn" href="<?= e(url('registers/technicians.php')) ?>">Technicians &amp; Skills</a>
      <?php if (Auth::can('reports.view')): ?>
        <a class="btn btn-primary" href="<?= e(url('reports/index.php')) ?>">Management Reports</a>
      <?php else: ?>
        <span class="btn locked" title="Reports are for Office Managers only">Management Reports (locked)</span>
      <?php endif; ?>
    </div>
    <p class="hint"><?= Auth::can('registers.edit') ? 'You can add and edit register entries.' : 'Registers are view only for your role.' ?>
      <?= Auth::can('reports.view') ? '' : ' Reports are disabled for your role.' ?></p>
  </section>
</div>

<section class="card">
  <h3>Database maintenance</h3>
  <div class="btn-grid">
    <form method="post" action="<?= e(url('maintenance/index.php')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="backup">
      <button class="btn btn-success" style="width:100%">Back Up Database Now</button>
    </form>
    <?php if (Auth::can('db.restore')): ?>
      <a class="btn btn-danger" href="<?= e(url('maintenance/index.php')) ?>#restore">Restore from Backup</a>
    <?php endif; ?>
    <?php if (Auth::can('db.compact')): ?>
      <a class="btn btn-danger" href="<?= e(url('maintenance/index.php')) ?>#compact">Compact / Optimise Database</a>
    <?php endif; ?>
    <?php if (Auth::can('db.check')): ?>
      <a class="btn btn-primary" href="<?= e(url('maintenance/index.php')) ?>#check">Integrity Check</a>
    <?php endif; ?>
    <?php if (Auth::can('users.manage')): ?>
      <a class="btn" href="<?= e(url('users/index.php')) ?>">User Accounts</a>
    <?php endif; ?>
  </div>
  <p class="hint">Backup: available to every user, and runs automatically every night at 18:00.
     Restore and Compact: Helpdesk Operators only. Integrity check: Administrator, monthly.</p>
</section>
<?php render('footer'); ?>
