<?php
/**
 * Protection against cross-site request forgery: every POST form carries a
 * secret token that must match the one stored in the session.
 */
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /** Hidden input to place inside every <form method="post">. */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    /** Call at the top of every POST handler. */
    public static function check(): void
    {
        $sent = $_POST['_csrf'] ?? '';
        if (!is_string($sent) || !hash_equals(self::token(), $sent)) {
            http_response_code(400);
            exit('Invalid or expired form. Please go back, reload the page and try again.');
        }
    }
}
