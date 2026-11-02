<?php
/**
 * Assign Technician (Design Document Figure 8) - Helpdesk Operators only.
 * Lists ONLY technicians skilled in the problem type, with their number of
 * open jobs, least busy first; the least busy available one is pre-selected
 * (load balancing, UR-08). A trigger refuses unskilled technicians (FR-09).
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::requirePermission('call.assign');

$problemNumber = parse_problem_ref(input('problem'));
$problem = $problemNumber
    ? Database::row('SELECT * FROM vw_ProblemDetail WHERE ProblemNumber = ?', [$problemNumber])
    : null;
$error = null;

if (is_post() && $problem) {
    Csrf::check();
    $techId = input('TechnicianID');
    if (!ctype_digit($techId)) {
        $error = 'Please choose a technician.';
    } else {
        try {
            Database::call('sp_AssignTechnician', [$problemNumber, (int)$techId]);
            flash('success', 'Technician assigned to ' . problem_ref($problemNumber) . '.');
            redirect('calls/view.php', ['problem' => $problemNumber]);
        } catch (PDOException $e) {
            $error = DbError::message($e);
        }
    }
}

render('header', ['title' => 'Assign Technician']);

if (!$problem):
    // No problem chosen: show open problems that still need a technician
    $waiting = Database::rows("SELECT ProblemNumber, ProblemRef, DateTimeLogged, ProblemType, Priority, ReporterName, OfficeName
                                 FROM vw_ProblemDetail WHERE Status = 'Open' AND TechnicianID IS NULL
                                ORDER BY FIELD(Priority, 'Critical', 'High', 'Medium', 'Low'), DateTimeLogged");
    ?>
    <section class="card">
      <form class="toolbar" method="get">
        <div><label for="problem">Problem No.</label><input id="problem" name="problem" placeholder="P-000128" value="<?= e(input('problem')) ?>"></div>
        <button class="btn btn-primary">Find</button>
      </form>
      <?php if (input('problem') !== ''): ?><div class="flash flash-error">Problem number <?= e(input('problem')) ?> does not exist.</div><?php endif; ?>
      <h3>Open problems waiting for a technician</h3>
      <?php if (!$waiting): ?><p>All open problems have a technician.</p><?php else: ?>
      <table class="data">
        <tr><th>Problem No.</th><th>Logged</th><th>Type</th><th>Priority</th><th>Reported by</th><th>Office</th><th></th></tr>
        <?php foreach ($waiting as $w): ?>
        <tr>
          <td><?= e($w['ProblemRef']) ?></td><td><?= e(fmt_datetime($w['DateTimeLogged'])) ?></td>
          <td><?= e($w['ProblemType']) ?></td><td><?= badge($w['Priority']) ?></td>
          <td><?= e($w['ReporterName']) ?></td><td><?= e($w['OfficeName']) ?></td>
          <td><a class="btn btn-small btn-primary" href="<?= e(url('calls/assign.php', ['problem' => $w['ProblemNumber']])) ?>">Assign</a></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </section>
    <?php
else:
    $techs = Database::call('sp_GetSkilledTechnicians', [(int)$problem['ProblemTypeID']])[0] ?? [];
    // recommend the least busy AVAILABLE technician (list is already sorted)
    $recommended = null;
    foreach ($techs as $t) {
        if ($t['Available'] === 'Yes') { $recommended = (int)$t['TechnicianID']; break; }
    }
    $selected = $problem['TechnicianID'] ? (int)$problem['TechnicianID'] : $recommended;
    ?>
    <section class="card">
      <div class="grid grid-4">
        <div><label>Problem No.</label><input readonly value="<?= e($problem['ProblemRef']) ?>"></div>
        <div><label>Problem type</label><input readonly value="<?= e($problem['ProblemType']) ?>"></div>
        <div><label>Priority</label><input readonly value="<?= e($problem['Priority']) ?>"></div>
        <div><label>Currently assigned</label><input readonly value="<?= e($problem['TechnicianName'] ?? 'Nobody') ?>"></div>
      </div>
    </section>

    <section class="card">
      <h3>Technicians skilled in "<?= e($problem['ProblemType']) ?>" (fewest open jobs first)</h3>
      <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
      <?php if ($problem['Status'] === 'Closed'): ?>
        <div class="flash flash-info">This problem is already closed.</div>
      <?php elseif (!$techs): ?>
        <div class="flash flash-error">No technician has this specialism. Ask the administrator to add one under Technicians &amp; Skills.</div>
      <?php else: ?>
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="problem" value="<?= e($problemNumber) ?>">
        <table class="data">
          <tr><th></th><th>Technician</th><th>Specialisms</th><th class="num">Open jobs</th><th>Available</th></tr>
          <?php foreach ($techs as $t): $id = (int)$t['TechnicianID']; ?>
          <tr class="<?= $id === $recommended ? 'recommended' : '' ?>">
            <td><input type="radio" name="TechnicianID" value="<?= $id ?>" <?= $id === $selected ? 'checked' : '' ?> style="width:auto"></td>
            <td><?= e($t['TechnicianName']) ?></td>
            <td><?= e($t['Specialisms']) ?></td>
            <td class="num"><?= (int)$t['OpenJobs'] ?></td>
            <td><?= e($t['Available']) ?><?= $id === $recommended ? ' - recommended' : '' ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <p class="note">Only technicians with this specialism are listed. Open jobs = problems assigned to them with Status = Open.</p>
        <div class="actions">
          <a class="btn" href="<?= e(url('calls/assign.php')) ?>">Back</a>
          <button class="btn btn-primary">Assign Selected Technician</button>
        </div>
      </form>
      <?php endif; ?>
    </section>
<?php endif;
render('footer');
