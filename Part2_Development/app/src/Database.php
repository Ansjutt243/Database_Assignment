<?php
/**
 * PDO connections to MySQL.
 * Database::forRole('Operator') connects with the MySQL account of that
 * role (least privilege, Design Document Section 11.3). Database::current()
 * uses the role of the logged-in user.
 */
declare(strict_types=1);

final class Database
{
    /** @var array<string, PDO> one connection per account per request */
    private static array $pool = [];

    public static function forRole(string $role): PDO
    {
        if (isset(self::$pool[$role])) {
            return self::$pool[$role];
        }
        $cfg = $GLOBALS['dbconfig'];
        if (!isset($cfg['accounts'][$role])) {
            throw new RuntimeException("No database account configured for role $role");
        }
        [$user, $pass] = $cfg['accounts'][$role];

        // Prefer the local Unix socket; fall back to TCP
        $dsn = file_exists($cfg['socket'])
            ?"mysql:unix_socket={$cfg['socket']};dbname={$cfg['database']};charset={$cfg['charset']}"
            : "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // errors become exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,                   // real prepared statements
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        return self::$pool[$role] = $pdo;
    }

    /** Connection for the role of the logged-in user. */
    public static function current(): PDO
    {
        return self::forRole(Auth::role() ?? 'Auth');
    }

    /** Run a prepared query and return all rows. */
    public static function rows(string $sql, array $params = [], ?PDO $pdo = null): array
    {
        $st = ($pdo ?? self::current())->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Run a prepared query and return the first row (or null). */
    public static function row(string $sql, array $params = [], ?PDO $pdo = null): ?array
    {
        $st = ($pdo ?? self::current())->prepare($sql);
        $st->execute($params);
        $r = $st->fetch();
        $st->closeCursor();
        return $r === false ? null : $r;
    }

    /** Run a prepared query and return the first column of the first row. */
    public static function value(string $sql, array $params = [], ?PDO $pdo = null): mixed
    {
        $r = self::row($sql, $params, $pdo);
        return $r === null ? null : reset($r);
    }

    /** Run INSERT / UPDATE / DELETE; returns affected rows. */
    public static function exec(string $sql, array $params = [], ?PDO $pdo = null): int
    {
        $st = ($pdo ?? self::current())->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /**
     * CALL a stored procedure and return every result set it produces.
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function call(string $procedure, array $params = [], ?PDO $pdo = null): array
    {
        $ph = implode(', ', array_fill(0, count($params), '?'));
        $st = ($pdo ?? self::current())->prepare("CALL $procedure($ph)");
        $st->execute(array_values($params));
        $sets = [];
        do {
            if ($st->columnCount() > 0) {
                $sets[] = $st->fetchAll();
            }
        } while ($st->nextRowset());
        $st->closeCursor();
        return $sets;
    }
}
