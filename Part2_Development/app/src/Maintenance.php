<?php
/**
 * Database maintenance (Design Document Section 12, Table 41).
 *   backup()         - all users (+ nightly schedule) - mysqldump to a time-stamped .sql file
 *   restore()        - operators only   - safety backup first, then load a backup
 *   compact()        - operators only   - OPTIMIZE TABLE via sp_CompactDatabase
 *   integrityCheck() - admin only       - CHECK TABLE + ANALYZE TABLE (monthly)
 * Every action is written to MaintenanceLog through sp_LogMaintenance,
 * which also refuses Restore/Compact for non-operators (second check).
 */
declare(strict_types=1);

final class Maintenance
{
    private const FILE_PATTERN = '/^mlhelpdesk_\d{8}_\d{4}(-\d+)?\.sql$/';

    public const TABLES = ['Department', 'Office', 'Employee', 'AppUser', 'Technician', 'ProblemType',
                           'TechnicianSpecialism', 'Manufacturer', 'EquipmentType', 'Hardware', 'Software',
                           'SoftwareLicence', 'InstalledSoftware', 'Problem', 'HelpdeskCall', 'MaintenanceLog'];

    /** @return array{ok: bool, file: ?string, message: string} */
    public static function backup(): array
    {
        $dir = config('backup_dir');
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
            return self::finish('Backup', null, false, 'Backup folder cannot be created.');
        }
        $name = 'mlhelpdesk_' . date('Ymd_Hi') . '.sql';
        for ($i = 2; file_exists("$dir/$name"); $i++) {
            $name = 'mlhelpdesk_' . date('Ymd_Hi') . "-$i.sql";
        }
        $path = "$dir/$name";

        [$code, $err] = self::runClient(config('mysqldump_bin'), 'Backup', [
            '--single-transaction',   // consistent snapshot without locking users out
            '--routines', '--triggers', '--events',
            '--no-tablespaces',
            '--result-file=' . $path,
        ]);

        $ok = $code === 0 && is_file($path) && filesize($path) > 0;
        if (!$ok) {
            @unlink($path);
            error_log('[' . date('c') . '] backup failed: ' . $err);
            return self::finish('Backup', $name, false, 'Backup failed. The error has been logged.');
        }
        self::prune();
        return self::finish('Backup', $name, true, "Backup saved as $name.");
    }

    /** Backup files, newest first. */
    public static function listBackups(): array
    {
        $files = [];
        foreach (glob(config('backup_dir') . '/mlhelpdesk_*.sql') ?: [] as $f) {
            if (preg_match(self::FILE_PATTERN, basename($f))) {
                $files[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
            }
        }
        usort($files, fn($a, $b) => $b['time'] <=> $a['time']);
        return $files;
    }

    /** @return array{ok: bool, file: ?string, message: string} */
    public static function restore(string $name): array
    {
        if (Auth::role() !== 'Operator') {                       // app-level check
            return ['ok' => false, 'file' => null, 'message' => 'Only helpdesk operators can restore the database.'];
        }
        // only a plain file name from the backup folder - no paths (prevents path traversal)
        if (!preg_match(self::FILE_PATTERN, $name) || !is_file(config('backup_dir') . "/$name")) {
            return ['ok' => false, 'file' => null, 'message' => 'Please choose a backup file from the list.'];
        }

        $safety = self::backup();                                // safety backup first
        if (!$safety['ok']) {
            return self::finish('Restore', $name, false, 'Restore cancelled: the safety backup failed.');
        }

        // MySQL deletes the EXECUTE rights on a procedure when the restore
        // drops it, so remember them now and grant them again afterwards.
        $rights = self::procedureRights();

        [$code, $err] = self::load($name);
        if ($code !== 0) {
            error_log('[' . date('c') . '] restore failed: ' . $err);
            // never leave a half-restored database: put the safety copy back
            [$back, $err2] = self::load($safety['file']);
            if ($back !== 0) {
                error_log('[' . date('c') . '] safety reload failed: ' . $err2);
            }
            self::regrant($rights);
            return self::finish('Restore', $name, false, $back === 0
                ? 'Restore failed, so the data from just before the restore was put back. The error has been logged.'
                : "Restore failed. Ask the administrator to restore {$safety['file']} manually.");
        }
        self::regrant($rights);
        return self::finish('Restore', $name, true,
            "Database restored from $name. A safety backup was saved as {$safety['file']}.");
    }

    /** EXECUTE rights on the database's procedures: [[user, host, procedure], ...] */
    private static function procedureRights(): array
    {
        return Database::rows("SELECT User, Host, Routine_name FROM mysql.procs_priv
                                WHERE Db = ? AND Routine_type = 'PROCEDURE'
                                  AND FIND_IN_SET('Execute', Proc_priv)",
                              [$GLOBALS['dbconfig']['database']], Database::forRole('Maint'));
    }

    /** Grants the remembered EXECUTE rights again after a restore. */
    private static function regrant(array $rights): void
    {
        $pdo = Database::forRole('Maint');
        $db  = $GLOBALS['dbconfig']['database'];
        $id  = fn(string $s) => '`' . str_replace('`', '``', $s) . '`';
        foreach ($rights as $r) {
            try {
                $pdo->exec("GRANT EXECUTE ON PROCEDURE {$id($db)}.{$id($r['Routine_name'])} TO "
                    . $pdo->quote($r['User']) . '@' . $pdo->quote($r['Host']));
            } catch (PDOException $e) {
                error_log('[' . date('c') . '] re-grant failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Loads a backup file into the database with the restore account.
     * Backups contain DEFINER=`root` clauses; the restore account may not
     * create objects for another user, so those clauses are removed.
     * @return array{0: int, 1: string}
     */
    private static function load(string $name): array
    {
        $sql = file_get_contents(config('backup_dir') . "/$name");
        $sql = preg_replace('/\/\*!50013 DEFINER=`[^`]+`@`[^`]+` SQL SECURITY DEFINER \*\/\n?/', '', $sql);
        $sql = preg_replace('/\/\*!50017 DEFINER=`[^`]+`@`[^`]+`\*\/\s*/', '', $sql);
        $sql = preg_replace('/CREATE DEFINER=`[^`]+`@`[^`]+`/', 'CREATE', $sql);
        return self::runClient(config('mysql_bin'), 'Maint', [], $sql);
    }

    /** @return array{ok: bool, file: ?string, message: string} */
    public static function compact(): array
    {
        if (Auth::role() !== 'Operator') {
            return ['ok' => false, 'file' => null, 'message' => 'Only helpdesk operators can compact the database.'];
        }
        try {
            Database::call('sp_CompactDatabase', [Auth::id()]);   // also writes the log row
            return ['ok' => true, 'file' => null, 'message' => 'Database compacted: all tables and indexes were rebuilt.'];
        } catch (PDOException $e) {
            return self::finish('Compact', null, false, DbError::message($e));
        }
    }

    /**
     * Monthly integrity check (Table 41) - System Administrator only.
     * CHECK TABLE finds corruption; ANALYZE TABLE refreshes index statistics.
     * MySQL does not allow CHECK TABLE inside a stored procedure, so it runs
     * here with the maintenance account after the Admin role is checked.
     * @return array{ok: bool, file: ?string, message: string, rows: array}
     */
    public static function integrityCheck(): array
    {
        if (Auth::role() !== 'Admin') {
            return ['ok' => false, 'file' => null, 'rows' => [],
                    'message' => 'Only the system administrator can run the integrity check.'];
        }
        $tables = implode(', ', self::TABLES);
        // MySQL on macOS/Windows reports names in lower case; show the real names
        $proper = array_combine(array_map('strtolower', self::TABLES), self::TABLES);
        $pdo = Database::forRole('Maint');
        $rows = [];
        $problems = 0;
        foreach (["CHECK TABLE $tables", "ANALYZE TABLE $tables"] as $sql) {
            foreach ($pdo->query($sql)->fetchAll() as $r) {
                $bad = in_array($r['Msg_type'], ['error', 'warning'], true)
                    || ($r['Op'] === 'check' && $r['Msg_type'] === 'status' && $r['Msg_text'] !== 'OK');
                $problems += $bad ? 1 : 0;
                $name = preg_replace('/^mlhelpdesk\./', '', $r['Table']);
                $rows[] = ['Table' => $proper[strtolower($name)] ?? $name,
                           'Operation' => $r['Op'], 'Result' => $r['Msg_text'], 'Status' => $bad ? 'Failed' : 'OK'];
            }
        }
        $ok = $problems === 0;
        $r = self::finish('Check', null, $ok, $ok
            ? 'Integrity check passed: all ' . count(self::TABLES) . ' tables are OK and their statistics were updated.'
            : "Integrity check found $problems problem(s). Restore the last good backup and contact ATS.");
        $r['rows'] = $rows;
        return $r;
    }

    /** Last successful backup time (for the Main Control Page). */
    public static function lastBackup(): ?string
    {
        return Database::value("SELECT MAX(ActionDateTime) FROM MaintenanceLog
                                 WHERE ActionType = 'Backup' AND Outcome = 'Success'");
    }

    /**
     * Writes the MaintenanceLog row and returns the result. With nobody
     * logged in (the scheduled nightly backup) the row has no user and is
     * written with the backup account.
     */
    private static function finish(string $action, ?string $file, bool $ok, string $message): array
    {
        try {
            $pdo = Auth::role() === null ? Database::forRole('Backup') : Database::current();
            Database::call('sp_LogMaintenance', [Auth::id(), $action, $file, $ok ? 'Success' : 'Failed'], $pdo);
        } catch (PDOException $e) {
            $ok = false;
            $message = DbError::message($e);
        }
        return ['ok' => $ok, 'file' => $file, 'message' => $message];
    }

    /** Keep only the newest N backups (Table 41: last 14 kept). */
    private static function prune(): void
    {
        foreach (array_slice(self::listBackups(), (int)config('backup_keep')) as $old) {
            @unlink(config('backup_dir') . '/' . $old['name']);
        }
    }

    /**
     * Runs mysqldump / mysql for the given account. The password goes in a
     * temporary option file, never on the command line (it would be visible
     * in the process list).
     * @return array{0: int, 1: string} exit code and error output
     */
    private static function runClient(string $bin, string $account, array $args, ?string $stdin = null): array
    {
        $cfg = $GLOBALS['dbconfig'];
        [$user, $pass] = $cfg['accounts'][$account];

        $opt = tempnam(sys_get_temp_dir(), 'mlh');
        chmod($opt, 0600);
        file_put_contents($opt, "[client]\nuser=\"$user\"\npassword=\"$pass\"\n"
            . ($cfg['host'] === 'localhost' && file_exists($cfg['socket']) ? "socket=\"{$cfg['socket']}\"\n" : '')
            . "host=\"{$cfg['host']}\"\nport={$cfg['port']}\n");

        $cmd = array_merge([$bin, '--defaults-extra-file=' . $opt], $args, [$cfg['database']]);
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            unlink($opt);
            return [1, "cannot start $bin"];
        }
        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        unlink($opt);
        return [$code, (string)$err];
    }
}
