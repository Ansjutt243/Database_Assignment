<?php
/**
 * Loaded first by every page in public/.
 * Sets up configuration, error handling, class loading and the session.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$GLOBALS['config']   = require APP_ROOT . '/config/config.php';
$GLOBALS['dbconfig'] = require APP_ROOT . '/config/database.php';

date_default_timezone_set($GLOBALS['config']['timezone']);

// Never show raw errors to users; write them to the log file instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $GLOBALS['config']['log_file']);
error_reporting(E_ALL);

spl_autoload_register(function (string $class): void {
    $file = APP_ROOT . '/src/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/src/helpers.php';

set_exception_handler(function (Throwable $e): void {
    error_log('[' . date('c') . '] ' . $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo '<h1>Something went wrong</h1><p>The error has been logged. '
       . 'Please try again or contact the system administrator.</p>';
});

// Scheduled jobs (app/cli/) run without a browser: no session or headers.
if (PHP_SAPI === 'cli') {
    return;
}

// HTTPS directly, or through a front end that tells us with X-Forwarded-Proto:
// the local Apache (deploy/apache) or a hosting proxy when TRUST_PROXY=1.
$https = !empty($_SERVER['HTTPS'])
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        && (($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1' || config('trust_proxy')));

// Secure session cookie (NFR-02): sent over HTTPS only when HTTPS is used
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => $https,
]);
session_name('MLHELPDESK');
session_start();

// Basic security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
