<?php
/**
 * Turns MySQL errors into messages a user can act on (NFR-03).
 * Layer 3 of validation (database) raises these errors; this class makes
 * sure the user sees "what is wrong and how to fix it", never raw SQL.
 */
declare(strict_types=1);

final class DbError
{
    /** CHECK / UNIQUE constraint name => friendly message */
    private const MESSAGES = [
        'chk_office_phone' => 'Enter a valid phone number (digits, spaces, + and - only).',
        'chk_office_spec'  => 'Choose a specialisation from the list.',
        'chk_emp_id'       => 'Employee ID must look like E0045.',
        'chk_emp_first'    => 'First name may contain letters, spaces, apostrophes and hyphens only.',
        'chk_emp_last'     => 'Last name may contain letters, spaces, apostrophes and hyphens only.',
        'chk_emp_phone'    => 'Enter a valid phone number (digits, spaces, + and - only).',
        'chk_emp_email'    => 'Enter a valid email address.',
        'chk_emp_dates'    => 'End date cannot be before start date.',
        'chk_user_name'    => 'Username must be 4-30 letters, digits, dots or underscores.',
        'chk_user_role'    => 'Choose a role from the list.',
        'chk_man_web'      => 'Website must start with http:// or https://',
        'chk_hw_serial'    => 'Serial number may contain letters, digits and - only.',
        'chk_hw_warranty'  => 'Warranty cannot end before purchase.',
        'chk_lic_seats'    => 'Seats must be 1 to 10,000.',
        'chk_lic_dates'    => 'Licence expiry cannot be before its start date.',
        'chk_description'  => 'Please describe the problem (min. 10 characters).',
        'chk_priority'     => 'Invalid priority.',
        'chk_hours'        => 'Time taken must be between 0.25 and 500 hours.',
        'chk_resolved'     => 'Resolved time must be after the logged time.',
        'chk_closed'       => 'Resolution date, notes and time taken are required to close a problem.',
        'chk_call_reason'  => 'Please enter the reason for the call.',
        'uq_department_name'  => 'This department already exists.',
        'uq_office_name'      => 'An office with this name already exists.',
        'uq_employee_email'   => 'This email address is already in use.',
        'PRIMARY'             => 'This ID already exists.',
        'uq_appuser_username' => 'This username is already taken.',
        'uq_appuser_employee' => 'This employee already has a log-in account.',
        'uq_technician_emp'   => 'This employee is already a technician.',
        'uq_hardware_serial'  => 'This serial number is already in the equipment register.',
        'uq_licence_number'   => 'This licence number already exists.',
        'uq_software_name_ver'=> 'This software name and version already exist.',
        'uq_manufacturer_name'=> 'This manufacturer already exists.',
    ];

    public static function message(PDOException $e): string
    {
        $code = (int)($e->errorInfo[1] ?? 0);
        $text = (string)($e->errorInfo[2] ?? $e->getMessage());

        if (($e->errorInfo[0] ?? '') === '45000') {     // SIGNAL from a trigger / procedure
            return $text;
        }
        if (preg_match("/constraint '([^']+)'/", $text, $m) || preg_match("/for key '(?:[^.']+\.)?([^']+)'/", $text, $m)) {
            if (isset(self::MESSAGES[$m[1]])) {
                return self::MESSAGES[$m[1]];
            }
        }
        return match ($code) {
            1062    => 'This value already exists - it must be unique.',
            1451, 1217 => 'This record is still used elsewhere, so it cannot be deleted.',
            1452    => 'A linked record does not exist. Please choose a value from the list.',
            1406    => 'One of the values is too long.',
            1142, 1370, 1143 => 'Your role does not have permission to do this.',
            default => self::logged($e),
        };
    }

    private static function logged(PDOException $e): string
    {
        error_log('[' . date('c') . '] DB ' . $e->getMessage());
        return 'The database could not save this. The error has been logged.';
    }
}
