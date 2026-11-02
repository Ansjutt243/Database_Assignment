<?php
/**
 * Authentication and role-based access control (Design Document Section 11).
 *
 * PERMISSIONS below is the access control matrix (Table 40) in code form.
 * Every page calls Auth::requirePermission(...) before doing anything.
 */
declare(strict_types=1);

final class Auth
{
    public const ROLES = ['Operator', 'Technician', 'Manager', 'Admin'];

    /** permission => roles allowed (Table 40) */
    public const PERMISSIONS = [
        'call.log'        => ['Operator'],                                  // log new call
        'call.followup'   => ['Operator'],                                  // follow-up call
        'call.assign'     => ['Operator'],                                  // assign technician
        'call.resolve'    => ['Operator', 'Technician'],                    // technician: own jobs
        'call.list'       => ['Operator', 'Technician', 'Manager', 'Admin'],// open problems list
        'call.view'       => ['Operator', 'Technician', 'Manager', 'Admin'],
        'reports.view'    => ['Manager'],                                   // R1-R6
        'registers.view'  => ['Operator', 'Technician', 'Manager', 'Admin'],
        'registers.edit'  => ['Admin'],
        'users.manage'    => ['Admin'],
        'db.backup'       => ['Operator', 'Technician', 'Manager', 'Admin'],// everyone
        'db.restore'      => ['Operator'],
        'db.compact'      => ['Operator'],
        'db.check'        => ['Admin'],                                     // monthly integrity check
    ];

    public const ROLE_LABELS = [
        'Operator'   => 'Helpdesk Operator',
        'Technician' => 'IT Technician',
        'Manager'    => 'Office Manager',
        'Admin'      => 'System Administrator',
    ];

    /**
     * Checks the username and password. Returns null on success or an
     * error message. Uses the ml_auth account, which can only read
     * AppUser/Employee and update the lock-out columns.
     */
    public static function attempt(string $username, string $password): ?string
    {
        $generic = 'Incorrect username or password.';   // never says which field was wrong
        if ($username === '' || $password === '') {
            return 'Please enter your username and password.';
        }

        $db = Database::forRole('Auth');
        $u = Database::row(
            'SELECT u.UserID, u.Username, u.PasswordHash, u.Role, u.IsActive, u.FailedLogins,
                    e.EmployeeID, e.FirstName, e.LastName, e.EndDate
               FROM AppUser u JOIN Employee e ON e.EmployeeID = u.EmployeeID
              WHERE u.Username = ?', [$username], $db);

        if ($u === null) {
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'); // same timing
            return $generic;
        }
        if (!$u['IsActive']) {
            return 'This account is locked. Please contact the system administrator.';
        }
        if ($u['EndDate'] !== null && $u['EndDate'] < date('Y-m-d')) {
            return 'This account belongs to an ex-employee and cannot be used.';
        }
        if (!password_verify($password, $u['PasswordHash'])) {
            $failed = (int)$u['FailedLogins'] + 1;
            $max    = (int)config('max_failed_logins');
            Database::exec('UPDATE AppUser SET FailedLogins = ?, IsActive = ? WHERE UserID = ?',
                [min($failed, $max), $failed >= $max ? 0 : 1, $u['UserID']], $db);
            return $failed >= $max
                ? 'Too many failed attempts. This account is now locked.'
                : $generic;
        }

        Database::exec('UPDATE AppUser SET FailedLogins = 0 WHERE UserID = ?', [$u['UserID']], $db);

        session_regenerate_id(true);   // prevents session fixation
        $_SESSION['user'] = [
            'id'          => (int)$u['UserID'],
            'username'    => $u['Username'],
            'role'        => $u['Role'],
            'employee_id' => $u['EmployeeID'],
            'name'        => $u['FirstName'] . ' ' . $u['LastName'],
        ];
        $_SESSION['last_activity'] = time();

        if ($u['Role'] === 'Technician') {
            $_SESSION['user']['technician_id'] = (int)Database::value(
                'SELECT TechnicianID FROM Technician WHERE EmployeeID = ?',
                [$u['EmployeeID']], Database::forRole('Technician'));
        }
        return null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function technicianId(): ?int
    {
        return $_SESSION['user']['technician_id'] ?? null;
    }

    public static function can(string $permission): bool
    {
        $role = self::role();
        return $role !== null && in_array($role, self::PERMISSIONS[$permission] ?? [], true);
    }

    /** Sends the user to the log-in page if not logged in or idle too long. */
    public static function requireLogin(): void
    {
        if (self::user() === null) {
            redirect('index.php');
        }
        if (time() - ($_SESSION['last_activity'] ?? 0) > (int)config('session_timeout')) {
            self::logout();
            flash('info', 'Your session expired after 20 minutes of inactivity. Please log in again.');
            redirect('index.php');
        }
        $_SESSION['last_activity'] = time();
    }

    /** Stops the page with "403 Access denied" unless the role has the permission. */
    public static function requirePermission(string $permission): void
    {
        self::requireLogin();
        if (!self::can($permission)) {
            http_response_code(403);
            render('denied', ['title' => 'Access denied']);
            exit;
        }
    }
}
