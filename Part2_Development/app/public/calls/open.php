<?php
/**
 * Open Problems List. Operators, managers and admin see all open problems;
 * technicians see their own jobs only (access matrix, Table 40).
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('call.list');

$isTech   = Auth::role() === 'Technician';
$priority = input('priority');
$office   = input('office');

$sql = "SELECT ProblemNumber, ProblemRef, DateTimeLogged, Priority, ProblemType, ReporterName,
               OfficeName, TechnicianName, CallCount
          FROM vw_ProblemDetail WHERE Status = 'Open'";
$params = [];
if ($isTech) {
    $sql .= ' AND TechnicianID = ?';
    $params[] = Auth::technicianId();
}
if (in_array($priority, Lookup::PRIORITIES, true)) {
    $sql .= ' AND Priority = ?';
    $params[] = $priority;
}
if (ctype_digit($office)) {
    $sql .= ' AND OfficeID = ?';
    $params[] = (int)$office;
}
$sql .= " ORDER BY FIELD(Priority, 'Critical', 'High', 'Medium', 'Low'), DateTimeLogged";
$rows = Database::rows($sql, $params);

render('header', ['title' => $isTech ? 'My Open Jobs' : 'Open Problems List']);
?>
<section class="card">
  <form class="toolbar" method="get">
    <div><label for="priority">Priority</label>
      <select id="priority" name="priority"><?= options(Lookup::same(Lookup::PRIORITIES), $priority, 'All') ?></select></div>
    <div><label for="office">Office</label>
      <select id="office" name="office"><?= options(Lookup::offices(), $office, 'All offices') ?></select></div>
    <button class="btn btn-primary">Filter</button>
  </form>
  <p><strong><?= count($rows) ?></strong> open problem(s).</p>
  <table class="data">
    <tr><th>Problem No.</th><th>Logged</th><th>Priority</th><th>Type</th><th>Reported by</th><th>Office</th>
        <th>Technician</th><th class="num">Calls</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['ProblemRef']) ?></td><td><?= e(fmt_datetime($r['DateTimeLogged'])) ?></td>
      <td><?= badge($r['Priority']) ?></td><td><?= e($r['ProblemType']) ?></td>
      <td><?= e($r['ReporterName']) ?></td><td><?= e($r['OfficeName']) ?></td>
      <td><?= e($r['TechnicianName'] ?? '(not assigned)') ?></td><td class="num"><?= (int)$r['CallCount'] ?></td>
      <td>
        <a class="btn btn-small" href="<?= e(url('calls/view.php', ['problem' => $r['ProblemNumber']])) ?>">View</a>
        <?php if (Auth::can('call.assign') && !$r['TechnicianName']): ?>
          <a class="btn btn-small btn-primary" href="<?= e(url('calls/assign.php', ['problem' => $r['ProblemNumber']])) ?>">Assign</a>
        <?php endif; ?>
        <?php if (Auth::can('call.resolve') && $r['TechnicianName']): ?>
          <a class="btn btn-small btn-success" href="<?= e(url('calls/resolve.php', ['problem' => $r['ProblemNumber']])) ?>">Resolve</a>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php render('footer'); ?>
