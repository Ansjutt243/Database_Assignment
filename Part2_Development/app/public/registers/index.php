<?php
/**
 * Registers menu. Every role can view; only the System Administrator can
 * add, edit or remove entries (access matrix, Table 40).
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('registers.view');

$counts = Database::row("SELECT
    (SELECT COUNT(*) FROM Office)          AS Offices,
    (SELECT COUNT(*) FROM Employee)        AS Employees,
    (SELECT COUNT(*) FROM Hardware)        AS Hardware,
    (SELECT COUNT(*) FROM Software)        AS Software,
    (SELECT COUNT(*) FROM SoftwareLicence) AS Licences,
    (SELECT COUNT(*) FROM Technician)      AS Technicians");

$registers = [
    ['registers/offices.php',     'Offices',               $counts['Offices'] . ' offices'],
    ['registers/employees.php',   'Employees',             $counts['Employees'] . ' employees incl. ex-employees'],
    ['registers/hardware.php',    'Hardware',              $counts['Hardware'] . ' items of office and network hardware'],
    ['registers/software.php',    'Software & Licences',   $counts['Software'] . ' products, ' . $counts['Licences'] . ' licences'],
    ['registers/technicians.php', 'Technicians & Skills',  $counts['Technicians'] . ' technicians'],
];

render('header', ['title' => 'Registers']);
?>
<section class="card">
  <h3>Registers</h3>
  <p class="hint"><?= Auth::can('registers.edit') ? 'You can add and edit entries.' : 'View only for your role. Only the System Administrator can change registers.' ?></p>
  <div class="grid grid-3">
    <?php foreach ($registers as [$link, $name, $info]): ?>
      <a class="card" style="text-decoration:none" href="<?= e(url($link)) ?>">
        <h3><?= e($name) ?></h3><span class="hint"><?= e($info) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php render('footer'); ?>
