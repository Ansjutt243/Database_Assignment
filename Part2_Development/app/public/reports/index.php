<?php
/**
 * Management Reports menu (Design Document Figure 10) - Office Managers only.
 * Helpdesk Operators get "Access denied" here, and their MySQL account has
 * no EXECUTE right on the report procedures either.
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('reports.view');

// months that have data, newest first
$months = [];
foreach (Database::rows("SELECT DISTINCT DATE_FORMAT(DateTimeLogged, '%Y-%m') AS m FROM Problem ORDER BY m DESC") as $r) {
    $months[$r['m']] = date('F Y', strtotime($r['m'] . '-01'));
}
$defaultMonth = array_key_first($months) ?? date('Y-m');

render('header', ['title' => 'Management Reports']);
?>
<form method="get" action="<?= e(url('reports/view.php')) ?>">
  <section class="card">
    <h3>Report parameters</h3>
    <div class="grid grid-3">
      <div><label for="month">Month</label>
        <select id="month" name="month"><?= options($months, $defaultMonth) ?></select></div>
      <div><label for="technician">Technician (R5)</label>
        <select id="technician" name="technician"><?= options(Lookup::technicians()) ?></select></div>
      <div><label for="office">Office (R6)</label>
        <select id="office" name="office"><?= options(Lookup::offices()) ?></select></div>
    </div>
    <label style="font-weight:normal"><input type="checkbox" name="alltime" value="1" style="width:auto"> R3: show all time instead of the chosen month</label>
  </section>

  <section class="card">
    <h3>Choose a report</h3>
    <table class="data">
      <tr><th>#</th><th>Report</th><th>Parameters</th><th></th></tr>
      <?php foreach (Reports::LIST as $code => $r): ?>
      <tr>
        <td><?= e($code) ?></td>
        <td><strong><?= e(substr($r['title'], 5)) ?></strong><br><span class="hint"><?= e($r['about']) ?></span></td>
        <td><?= e(implode(', ', array_map(fn($p) => ['month' => 'Month', 'month_optional' => 'Month (optional)',
              'technician' => 'Technician', 'office' => 'Office'][$p], $r['params'])) ?: 'None') ?></td>
        <td><button class="btn btn-primary btn-small" name="r" value="<?= e($code) ?>">View on screen</button></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <p class="hint">Each report can be printed or saved as PDF from the report page. Helpdesk Operators cannot open this page.</p>
  </section>
</form>
<?php render('footer'); ?>
