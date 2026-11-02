<?php
/**
 * MySQL connection settings (Design Document Section 11.3).
 * Every application role connects with its own MySQL account, so the
 * database itself enforces least privilege even if the app is bypassed.
 * Accounts are created by database/04_security_roles.sql.
 *
 * On a server (e.g. Railway) every value comes from an environment
 * variable; the defaults below are the local pilot settings.
 */
$env = fn(string $name, string $default) => getenv($name) !== false && getenv($name) !== '' ? getenv($name) : $default;

return [
    'host'     => $env('DB_HOST', 'localhost'),
    'socket'   => $env('DB_SOCKET', '/tmp/mysql.sock'),
    'port'     => (int)$env('DB_PORT', '3306'),
    'database' => $env('DB_NAME', 'mlhelpdesk'),
    'charset'  => 'utf8mb4',

    'accounts' => [
        // application role => [MySQL user, password]
        'Auth'       => ['ml_auth',       $env('ML_AUTH_PASSWORD',       'Auth#Pilot2026!')],  // log-in page only
        'Operator'   => ['ml_operator',   $env('ML_OPERATOR_PASSWORD',   'Op#Pilot2026!')],
        'Technician' => ['ml_technician', $env('ML_TECHNICIAN_PASSWORD', 'Tech#Pilot2026!')],
        'Manager'    => ['ml_manager',    $env('ML_MANAGER_PASSWORD',    'Mgr#Pilot2026!')],
        'Admin'      => ['ml_admin',      $env('ML_ADMIN_PASSWORD',      'Adm#Pilot2026!')],
        'Backup'     => ['ml_backup',     $env('ML_BACKUP_PASSWORD',     'Bak#Pilot2026!')],   // mysqldump, all users
        'Maint'      => ['ml_maint',      $env('ML_MAINT_PASSWORD',      'Mnt#Pilot2026!')],   // restore, operators only
    ],
];
