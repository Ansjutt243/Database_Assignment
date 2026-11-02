<?php
/**
 * Scheduled nightly backup (Design Document Table 41: "also scheduled
 * nightly at 18:00"). Run by the operating system's scheduler:
 *   macOS   - launchd   (deploy/schedule/install_schedule.sh)
 *   Linux   - cron      0 18 * * 1-6  php app/cli/scheduled_backup.php
 *   Windows - Task Scheduler, daily 18:00
 * The backup is logged in MaintenanceLog as "Scheduled backup" (no user).
 * Exit code 0 = success, 1 = failure (so the scheduler can report it).
 */
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$result = Maintenance::backup();
$line = '[' . date('Y-m-d H:i:s') . '] scheduled backup: ' . ($result['ok'] ? 'OK ' : 'FAILED ') . $result['message'];
echo $line, PHP_EOL;
error_log($line);
exit($result['ok'] ? 0 : 1);
