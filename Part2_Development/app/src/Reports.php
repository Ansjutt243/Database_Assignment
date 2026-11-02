<?php
/**
 * Management reports R1-R6 (Design Document Section 10).
 * Each report is a stored procedure in the database; this class describes
 * the parameters each one needs and the heading of each result table.
 */
declare(strict_types=1);

final class Reports
{
    public const LIST = [
        'R1' => [
            'title'  => 'R1 - Monthly Calls Summary',
            'about'  => 'Total calls logged in the month, open and closed cases, and time spent on each closed case.',
            'params' => ['month'],
            'tables' => ['Summary', 'Closed cases'],
        ],
        'R2' => [
            'title'  => 'R2 - Technician Job Sheet Summary',
            'about'  => 'Each technician: jobs worked, open and closed cases and total time spent in the month.',
            'params' => ['month'],
            'tables' => ['Technicians', 'Totals'],
        ],
        'R3' => [
            'title'  => 'R3 - Jobs Logged by Office',
            'about'  => 'Each office: hardware faults, software faults, time spent and number of different technicians.',
            'params' => ['month_optional'],
            'tables' => ['Offices'],
        ],
        'R4' => [
            'title'  => 'R4 - Jobs per Equipment Register Item',
            'about'  => 'Jobs per hardware item and software product, to find items to repair under warranty/licence or replace.',
            'params' => [],
            'tables' => ['Hardware items', 'Software products'],
        ],
        'R5' => [
            'title'  => 'R5 - Technician Job Detail',
            'about'  => 'Jobs of one technician in the month with office, job number and equipment; totals and average time.',
            'params' => ['technician', 'month'],
            'tables' => ['Jobs', 'Totals'],
        ],
        'R6' => [
            'title'  => 'R6 - Office Job Detail',
            'about'  => 'Jobs logged by one office in the month, open/closed totals and jobs per register item.',
            'params' => ['office', 'month'],
            'tables' => ['Jobs', 'Totals', 'Jobs per register item'],
        ],
    ];

    /**
     * Runs a report. $month is "YYYY-MM" (or '' for all-time R3).
     * @return array<int, array<int, array<string, mixed>>> result sets
     */
    public static function run(string $code, string $month, ?int $technicianId, ?int $officeId): array
    {
        [$y, $m] = $month !== '' ? array_map('intval', explode('-', $month)) : [null, null];
        return match ($code) {
            'R1' => Database::call('sp_Report_R1', [$y, $m]),
            'R2' => Database::call('sp_Report_R2', [$y, $m]),
            'R3' => Database::call('sp_Report_R3', [$y, $m]),
            'R4' => Database::call('sp_Report_R4', []),
            'R5' => Database::call('sp_Report_R5', [$technicianId, $y, $m]),
            'R6' => Database::call('sp_Report_R6', [$officeId, $y, $m]),
        };
    }

    /** Text describing the chosen parameters, for the report header. */
    public static function describeParams(string $code, string $month, ?string $technician, ?string $office): string
    {
        $parts = [];
        $p = self::LIST[$code]['params'];
        if (in_array('technician', $p, true)) { $parts[] = 'Technician: ' . $technician; }
        if (in_array('office', $p, true))     { $parts[] = 'Office: ' . $office; }
        if (in_array('month', $p, true) || in_array('month_optional', $p, true)) {
            $parts[] = 'Month: ' . ($month !== '' ? date('F Y', strtotime($month . '-01')) : 'All time');
        }
        return $parts ? implode('  |  ', $parts) : 'Period: all time (with last 90 days)';
    }
}
