<?php
/**
 * Shows one management report (R1-R6) with the standard layout from
 * Design Document Section 10: header (name, parameters, run by, date),
 * body tables, totals and a confidentiality footer. Print / Save as PDF
 * uses the browser's print function with a print stylesheet.
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('reports.view');

$code = input('r');
if (!isset(Reports::LIST[$code])) {
    flash('error', 'Please choose a report.');
    redirect('reports/index.php');
}
$def   = Reports::LIST[$code];
$month = preg_match('/^\d{4}-\d{2}$/', input('month')) ? input('month') : date('Y-m');
if ($code === 'R3' && input('alltime') === '1') {
    $month = '';
}
$techId   = ctype_digit(input('technician')) ? (int)input('technician') : null;
$officeId = ctype_digit(input('office')) ? (int)input('office') : null;

$sets = Reports::run($code, $month, $techId, $officeId);
$params = Reports::describeParams($code, $month,
    Lookup::technicians()[$techId] ?? '—', Lookup::offices()[$officeId] ?? '—');

/** A column is numeric (right-aligned) when every value in it is a number. */
$numericColumns = function (array $rows): array {
    $num = [];
    foreach (array_keys($rows[0]) as $c) {
        $vals = array_filter(array_column($rows, $c), fn($v) => $v !== null);
        $num[$c] = $vals && count(array_filter($vals, 'is_numeric')) === count($vals);
    }
    return $num;
};

render('header', ['title' => $def['title']]);
?>
<section class="card">
  <div class="actions no-print" style="margin-top:0">
    <a class="btn" href="<?= e(url('reports/index.php')) ?>">Back to reports</a>
    <button class="btn btn-primary" data-print>Print / Save as PDF</button>
  </div>
  <h2><?= e($def['title']) ?></h2>
  <dl class="report-head">
    <dt>Report</dt><dd><?= e($def['about']) ?></dd>
    <dt>Parameters</dt><dd><?= e($params) ?></dd>
    <dt>Run by / date</dt><dd><?= e(Auth::user()['name'] . ' (' . Auth::ROLE_LABELS[Auth::role()] . ') / ' . date('d/m/Y H:i')) ?></dd>
  </dl>

  <?php foreach ($sets as $i => $rows): if (!isset($def['tables'][$i])) { continue; } /* ignore the procedure's status result */ ?>
    <h3><?= e($def['tables'][$i] ?? '') ?></h3>
    <?php if (!$rows): ?>
      <p class="hint">No data for these parameters.</p>
    <?php else: $cols = array_keys($rows[0]); $isNum = $numericColumns($rows); ?>
      <table class="data">
        <tr><?php foreach ($cols as $c): ?><th class="<?= $isNum[$c] ? 'num' : '' ?>"><?= e(preg_replace('/(?<=[a-z])(?=[A-Z0-9])|(?<=[0-9])(?=[A-Z])/', ' ', $c)) ?></th><?php endforeach; ?></tr>
        <?php foreach ($rows as $row): ?>
          <tr class="<?= in_array($def['tables'][$i], ['Summary', 'Totals'], true) ? 'total' : '' ?>">
          <?php foreach ($cols as $c): $v = $row[$c]; ?>
            <td class="<?= $isNum[$c] ? 'num' : '' ?>"><?php
              if ($v === null) { echo '—'; }
              elseif (in_array($c, ['Warranty', 'Licence', 'Action', 'Status', 'Flag'], true)) { echo badge((string)$v); }
              elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v)) { echo e(fmt_date($v)); }
              else { echo e($v); }
            ?></td>
          <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  <?php endforeach; ?>

  <div class="report-foot">Confidential - ML internal use only &middot; Generated <?= e(date('d/m/Y H:i')) ?></div>
</section>
<?php render('footer'); ?>
