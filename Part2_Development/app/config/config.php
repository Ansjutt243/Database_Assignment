<?php
/**
 * Application settings for the ML IT Helpdesk System.
 * Values marked "pilot" must be reviewed before go-live.
 */
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
    'mysqldump_bin'   => getenv('MYSQLDUMP_BIN') ?: getenv('HOME') . '/mysql/bin/mysqldump',
    'mysql_bin'       => getenv('MYSQL_BIN') ?: getenv('HOME') . '/mysql/bin/mysql',

    // Behind a hosting proxy that terminates HTTPS (e.g. Railway) set
    // TRUST_PROXY=1 so X-Forwarded-Proto is trusted for the Secure cookie.
    'trust_proxy'     => getenv('TRUST_PROXY') === '1',

    // Errors are written here, never shown to users
    'log_file'        => dirname(__DIR__) . '/storage/logs/app.log',
];
