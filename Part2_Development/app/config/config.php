<?php
/**
 * Application settings for the ML IT Helpdesk System.
 * Values marked "pilot" must be reviewed before go-live.
 */

/**
 * Finds the MySQL client tools (used for backup and restore) on Windows,
 * macOS and Linux: the usual install folders first, then the PATH.
 */
function mysql_client(string $tool): string
{
    if (PHP_OS_FAMILY === 'Windows') {
        $dirs = glob('C:/Program Files/MySQL/MySQL Server */bin') ?: [];
        rsort($dirs, SORT_NATURAL);                          // newest MySQL version first
        $tool .= '.exe';
    } else {
        $dirs = [getenv('HOME') . '/mysql/bin', '/usr/local/mysql/bin', '/opt/homebrew/bin', '/usr/local/bin', '/usr/bin'];
    }
    foreach ($dirs as $d) {
        if (is_file("$d/$tool")) {
            return "$d/$tool";
        }
    }
    return $tool;                                            // else rely on the PATH
}

return [
    'app_name'        => 'ML IT Helpdesk',
    'timezone'        => 'Asia/Karachi',

    // Base URL path of the public/ folder. '' when public/ is the web root.
    'base_url'        => '',

    // NFR-02: session expires after 20 minutes idle; lock after 5 failed log-ins
    'session_timeout' => 20 * 60,
    'max_failed_logins' => 5,

    // Section 12: backups (time-stamped .sql files, last 14 kept)
    'backup_dir'      => dirname(__DIR__) . '/storage/backups',
    'backup_keep'     => 14,
    'mysqldump_bin'   => getenv('MYSQLDUMP_BIN') ?: mysql_client('mysqldump'),
    'mysql_bin'       => getenv('MYSQL_BIN') ?: mysql_client('mysql'),

    // Behind a hosting proxy that terminates HTTPS (e.g. Railway) set
    // TRUST_PROXY=1 so X-Forwarded-Proto is trusted for the Secure cookie.
    'trust_proxy'     => getenv('TRUST_PROXY') === '1',

    // Errors are written here, never shown to users
    'log_file'        => dirname(__DIR__) . '/storage/logs/app.log',
];
