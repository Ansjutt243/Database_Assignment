<?php
/**
 * Database Maintenance (Design Document Figure 12, Section 12).
 *   Back up  - every user
 *   Restore  - Helpdesk Operators only; must type the backup date to confirm
 *   Compact  - Helpdesk Operators only
 *   Integrity check - System Administrator only (monthly, Table 41)
 * The maintenance log is shown to everyone.
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('db.backup');

if (is_post()) {
    Csrf::check();
    $action = input('action');
    if ($action === 'backup') {
        $r = Maintenance::backup();
    } elseif ($action === 'restore') {
        Auth::requirePermission('db.restore');
        $file = input('BackupFile');
        // Verification (8.4): the operator types the date part of the file name
        $date = preg_match('/^mlhelpdesk_(\d{8})_/', $file, $m) ? $m[1] : null;
        $r = $date !== null && input('ConfirmDate') === $date
            ? Maintenance::restore($file)
            : ['ok' => false, 'message' => 'Restore cancelled: type the backup date (YYYYMMDD) exactly to confirm.'];
    } elseif ($action === 'compact') {
        Auth::requirePermission('db.compact');
        $r = Maintenance::compact();
    } elseif ($action === 'check') {
        Auth::requirePermission('db.check');
        $r = Maintenance::integrityCheck();
        $_SESSION['integrity_rows'] = $r['rows'];      // shown once on the next page
    } else {
        $r = ['ok' => false, 'message' => 'Unknown action.'];
    }
    flash($r['ok'] ? 'success' : 'error', $r['message']);
    redirect('maintenance/index.php');
}

$backups = Maintenance::listBackups();
$integrityRows = $_SESSION['integrity_rows'] ?? [];
unset($_SESSION['integrity_rows']);
$lastCheck = Database::value("SELECT MAX(ActionDateTime) FROM vw_MaintenanceLog WHERE ActionType = 'Check'");
$log = Database::rows('SELECT * FROM vw_MaintenanceLog ORDER BY ActionDateTime DESC, LogID DESC LIMIT 30');

render('header', ['title' => 'Database Maintenance']);
?>
<div class="grid grid-3">
  <section class="card">
    <h3>Back up</h3>
    <p>Creates a time-stamped .sql copy of the whole database (tables, data, views, triggers and procedures).</p>
    <form method="post"><?= Csrf::field() ?>
      <button class="btn btn-success" name="action" value="backup" style="width:100%">Back Up Now</button></form>
    <p class="hint">Available to every user. The last <?= (int)config('backup_keep') ?> backups are kept.</p>
  </section>

  <section class="card" id="restore">
    <h3>Restore (Operators only)</h3>
    <?php if (Auth::can('db.restore')): ?>
      <?php if (!$backups): ?><p>No backups yet. Make a backup first.</p><?php else: ?>
      <form method="post" data-confirm="Restore will REPLACE all current data with the chosen backup. A safety backup is taken first. Continue?">
        <?= Csrf::field() ?>
        <label for="BackupFile">Backup file</label>
        <select id="BackupFile" name="BackupFile" required>
          <?= options(array_combine(array_column($backups, 'name'),
                array_map(fn($b) => $b['name'] . ' (' . round($b['size'] / 1024) . ' KB)', $backups))) ?>
        </select>
        <label for="ConfirmDate">Type the backup date (YYYYMMDD) to confirm</label>
        <input id="ConfirmDate" name="ConfirmDate" required pattern="\d{8}" maxlength="8" placeholder="e.g. <?= e(date('Ymd')) ?>">
        <div class="actions"><button class="btn btn-danger" name="action" value="restore" style="width:100%">Restore</button></div>
      </form>
      <?php endif; ?>
    <?php else: ?>
      <p class="hint">Only Helpdesk Operators can restore the database.</p>
    <?php endif; ?>
  </section>

  <section class="card" id="compact">
    <h3>Compact (Operators only)</h3>
    <p>Rebuilds all tables and indexes to reclaim unused space (OPTIMIZE TABLE). Best run outside busy hours.</p>
    <?php if (Auth::can('db.compact')): ?>
      <form method="post" data-confirm="Compact the database now? Users may notice a short delay."><?= Csrf::field() ?>
        <button class="btn btn-danger" name="action" value="compact" style="width:100%">Compact Now</button></form>
    <?php else: ?>
      <p class="hint">Only Helpdesk Operators can compact the database.</p>
    <?php endif; ?>
  </section>
</div>

<section class="card" id="check">
  <h3>Integrity check (Administrator, monthly)</h3>
  <p>Checks every table for corruption (CHECK TABLE) and updates index statistics so queries stay fast (ANALYZE TABLE).
     Last run: <strong><?= $lastCheck ? e(fmt_datetime($lastCheck)) : 'never' ?></strong>.</p>
  <?php if (Auth::can('db.check')): ?>
    <form method="post"><?= Csrf::field() ?>
      <button class="btn btn-primary" name="action" value="check">Run Integrity Check</button></form>
  <?php else: ?>
    <p class="hint">Only the System Administrator can run the integrity check.</p>
  <?php endif; ?>
  <?php if ($integrityRows): ?>
    <table class="data" style="margin-top:12px">
      <tr><th>Table</th><th>Operation</th><th>Result</th><th>Status</th></tr>
      <?php foreach ($integrityRows as $r): ?>
        <tr><td><?= e($r['Table']) ?></td><td><?= e($r['Operation']) ?></td><td><?= e($r['Result']) ?></td><td><?= badge($r['Status']) ?></td></tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</section>

<section class="card">
  <h3>Maintenance log</h3>
  <table class="data">
    <tr><th>Date/time</th><th>User</th><th>Role</th><th>Action</th><th>File</th><th>Outcome</th></tr>
    <?php foreach ($log as $l): ?>
    <tr><td><?= e(fmt_datetime($l['ActionDateTime'])) ?></td><td><?= e($l['FullName']) ?></td><td><?= e($l['Role']) ?></td>
      <td><?= e($l['ActionType']) ?></td><td><?= e($l['BackupFile'] ?? '-') ?></td><td><?= badge($l['Outcome']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <p class="hint">Restore asks for confirmation and takes a safety backup first.</p>
</section>
<?php render('footer'); ?>
