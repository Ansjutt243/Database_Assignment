<?php
/**
 * Performance test for NFR-01 (Design Document Table 5):
 *   - caller and equipment look-ups return in under 2 seconds
 *   - each report runs in under 5 seconds with 50,000 problem records
 *
 * 1. Adds 50,000 generated problems (+ one call each), marked "PERFTEST".
 * 2. Times every look-up (Operator account) and report (Manager account),
 *    3 runs each, through the same Database class the application uses.
 * 3. Deletes the generated rows again (also if the test fails).
 * 4. Writes the results to tests/performance_results.md.
 *
 * Usage:  ~/php/php tests/performance_test.php     (asks for the MySQL root password)
 */
require dirname(__DIR__) . '/app/src/bootstrap.php';

const ROWS  = 50000;
const RUNS  = 3;
const MONTH = [2026, 6];        // a month inside the generated data

$pw = getenv('MYSQL_ROOT_PW');
if ($pw === false) {
    echo 'MySQL root password: ';
    system('stty -echo');
    $pw = trim((string)fgets(STDIN));
    system('stty echo');
    echo PHP_EOL;
}
$cfg  = $GLOBALS['dbconfig'];
$root = new PDO("mysql:unix_socket={$cfg['socket']};dbname={$cfg['database']};charset=utf8mb4", 'root', $pw,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

function cleanup(PDO $root): void
{
    $root->exec("DELETE c FROM HelpdeskCall c JOIN Problem p ON p.ProblemNumber = c.ProblemNumber
                  WHERE p.Description LIKE 'PERFTEST%'");
    $root->exec("DELETE FROM Problem WHERE Description LIKE 'PERFTEST%'");
    $root->exec('ALTER TABLE Problem AUTO_INCREMENT = 1');        // back to MAX + 1
    $root->exec('ALTER TABLE HelpdeskCall AUTO_INCREMENT = 1');
}

cleanup($root);                                                    // in case an old run was interrupted
$before = (int)$root->query('SELECT COUNT(*) FROM Problem')->fetchColumn();

try {
    // ---------------------------------------------------------- 1. generate data
    echo 'Generating ' . number_format(ROWS) . ' problems and calls ... ';
    $t = microtime(true);
    $root->exec('SET SESSION cte_max_recursion_depth = ' . (ROWS + 10));
    $root->exec("
        INSERT INTO Problem (ReportedByEmployeeID, ProblemTypeID, HardwareID, Description, Priority, Status,
                             DateTimeLogged, TechnicianID, DateTimeAssigned, DateTimeResolved,
                             ResolutionNotes, TimeTakenHours, ResolvedByUserID)
        WITH RECURSIVE seq (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM seq WHERE n < " . ROWS . "),
        emp AS (SELECT EmployeeID, ROW_NUMBER() OVER (ORDER BY EmployeeID) - 1 AS i
                  FROM Employee WHERE EndDate IS NULL AND StartDate <= '2025-11-01'),
        ts  AS (SELECT TechnicianID, ProblemTypeID, ROW_NUMBER() OVER (ORDER BY TechnicianID, ProblemTypeID) - 1 AS i
                  FROM TechnicianSpecialism),
        hw  AS (SELECT HardwareID, ROW_NUMBER() OVER (ORDER BY HardwareID) - 1 AS i FROM Hardware)
        SELECT e.EmployeeID, t.ProblemTypeID, h.HardwareID,
               CONCAT('PERFTEST generated problem ', s.n),
               ELT(1 + s.n % 4, 'Low', 'Medium', 'High', 'Critical'),
               IF(s.n % 5 = 0, 'Open', 'Closed'),
               TIMESTAMP('2025-11-01 08:00:00') + INTERVAL (s.n * 10) MINUTE,
               t.TechnicianID,
               TIMESTAMP('2025-11-01 08:10:00') + INTERVAL (s.n * 10) MINUTE,
               IF(s.n % 5 = 0, NULL, TIMESTAMP('2025-11-01 10:00:00') + INTERVAL (s.n * 10) MINUTE),
               IF(s.n % 5 = 0, NULL, 'Generated resolution for the performance test'),
               IF(s.n % 5 = 0, NULL, 1.50),
               IF(s.n % 5 = 0, NULL, 1)
          FROM seq s
          JOIN emp e ON e.i = s.n % (SELECT COUNT(*) FROM emp)
          JOIN ts  t ON t.i = s.n % (SELECT COUNT(*) FROM ts)
          JOIN hw  h ON h.i = s.n % (SELECT COUNT(*) FROM hw)");
    $root->exec("
        INSERT INTO HelpdeskCall (ProblemNumber, CallerEmployeeID, OperatorUserID, CallDateTime,
                                  CallType, CallReason, UrgencyLevel)
        SELECT ProblemNumber, ReportedByEmployeeID, 1, DateTimeLogged, 'New',
               'Generated call for the performance test', 'Normal'
          FROM Problem WHERE Description LIKE 'PERFTEST%'");
    $root->query('ANALYZE TABLE Problem, HelpdeskCall')->fetchAll();   // returns a result set
    $total = (int)$root->query('SELECT COUNT(*) FROM Problem')->fetchColumn();
    printf("done in %.1f s (%s problems in total)\n", microtime(true) - $t, number_format($total));

    // ---------------------------------------------------------- 2. time look-ups and reports
    [$y, $m] = MONTH;
    $tests = [
        // [requirement, description, role, limit (s), callable returning row count]
        ['UR-02', 'Caller search by partial name', 'Operator', 2, fn() => count(Database::rows(
            "SELECT e.EmployeeID, e.JobTitle, d.DepartmentName, o.OfficeName FROM Employee e
               JOIN Department d ON d.DepartmentID = e.DepartmentID JOIN Office o ON o.OfficeID = e.OfficeID
              WHERE CONCAT(e.FirstName, ' ', e.LastName) LIKE ?", ['%ahm%'], Database::forRole('Operator')))],
        ['UR-03', 'Equipment look-up by serial + warranty', 'Operator', 2, fn() => count(Database::rows(
            'SELECT * FROM vw_HardwareStatus WHERE SerialNumber = ?', ['HP-5CG1234XYZ'], Database::forRole('Operator')))],
        ['UR-04', 'Installed software + licence status', 'Operator', 2, fn() => count(Database::rows(
            'SELECT l.* FROM InstalledSoftware i JOIN vw_LicenceStatus l ON l.LicenceID = i.LicenceID
              WHERE i.HardwareID = 1', [], Database::forRole('Operator')))],
        ['UR-08', 'Skilled technicians with open-job count', 'Operator', 2, fn() => count(
            Database::call('sp_GetSkilledTechnicians', [11], Database::forRole('Operator'))[0])],
        ['UR-06', 'Find problem by number (follow-up)', 'Operator', 2, fn() => count(Database::rows(
            'SELECT * FROM vw_ProblemDetail WHERE ProblemNumber = ?', [$total - 5], Database::forRole('Operator')))],
    ];
    foreach (['R1' => [$y, $m], 'R2' => [$y, $m], 'R3' => [$y, $m], 'R3 (all time)' => [null, null],
              'R4' => [], 'R5' => [3, $y, $m], 'R6' => [1, $y, $m]] as $name => $args) {
        $proc = 'sp_Report_' . substr($name, 0, 2);
        $tests[] = ['UR-12', "Report $name", 'Manager', 5, fn() => array_sum(array_map('count',
            Database::call($proc, $args, Database::forRole('Manager'))))];
    }

    $results = [];
    foreach ($tests as [$req, $desc, $role, $limit, $fn]) {
        $times = [];
        for ($i = 0; $i < RUNS; $i++) {
            $t = microtime(true);
            $rows = $fn();
            $times[] = microtime(true) - $t;
        }
        $avg = array_sum($times) / RUNS;
        $max = max($times);
        $results[] = [$req, $desc, $role, $rows, $avg, $max, $limit, $max < $limit];
        printf("%-48s %8.3f s  %s\n", $desc, $max, $max < $limit ? 'PASS' : 'FAIL');
    }
} finally {
    // ---------------------------------------------------------- 3. remove test data
    cleanup($root);
    $after = (int)$root->query('SELECT COUNT(*) FROM Problem')->fetchColumn();
    echo "Generated data removed (problems before: $before, after: $after).\n";
}

// -------------------------------------------------------------- 4. write report
$md  = "# Performance test results (NFR-01)\n\n";
$md .= 'Run on ' . date('d/m/Y H:i') . ' with **' . number_format($total) . " problem records** and the same number of calls";
$md .= " (MySQL 8.0, PHP 8.2, pilot laptop). Each test was run " . RUNS . " times; *Slowest* is the figure checked against the limit.\n\n";
$md .= "| Req. | Test | MySQL account | Rows returned | Average (s) | Slowest (s) | Limit (s) | Result |\n|---|---|---|---:|---:|---:|---:|---|\n";
foreach ($results as [$req, $desc, $role, $rows, $avg, $max, $limit, $ok]) {
    $md .= sprintf("| %s | %s | %s | %s | %.3f | %.3f | %d | %s |\n", $req, $desc, 'ml_' . strtolower($role),
        number_format($rows), $avg, $max, $limit, $ok ? 'PASS' : 'FAIL');
}
$passed = count(array_filter($results, fn($r) => $r[7]));
$md .= "\n**$passed of " . count($results) . " tests within the NFR-01 limits.** The generated rows were deleted after the test.\n";
file_put_contents(__DIR__ . '/performance_results.md', $md);
echo "Results written to tests/performance_results.md\n";
