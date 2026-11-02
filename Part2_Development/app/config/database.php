<?php
/**
 * MySQL connection settings (Design Document Section 11.3).
 * Every application role connects with its own MySQL account, so the
 * database itself enforces least privilege even if the app is bypassed.
 * Accounts are created by database/04_security_roles.sql.
 * Pilot passwords - change them (here and in MySQL) before go-live.
 */
return [
    'host'     => 'localhost',
    'socket'   => '/tmp/mysql.sock',
    'port'     => 3306,
    'database' => 'mlhelpdesk',
    'charset'  => 'utf8mb4',

    'accounts' => [
        // application role => [MySQL user, password]
        'Auth'       => ['ml_auth',       'Auth#Pilot2026!'],  // log-in page only
        'Operator'   => ['ml_operator',   'Op#Pilot2026!'],
        'Technician' => ['ml_technician', 'Tech#Pilot2026!'],
        'Manager'    => ['ml_manager',    'Mgr#Pilot2026!'],
        'Admin'      => ['ml_admin',      'Adm#Pilot2026!'],
        'Backup'     => ['ml_backup',     'Bak#Pilot2026!'],   // mysqldump, all users
        'Maint'      => ['ml_maint',      'Mnt#Pilot2026!'],   // restore, operators only
    ],
];
