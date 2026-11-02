<?php
/**
 * Server-side validation - layer 2 of the three validation layers
 * (Design Document Section 8.1). Browser checks can be bypassed, so every
 * value is checked again here before it reaches the database.
 *
 * Usage:
 *   $v = new Validator($_POST);
 *   $v->required('CallReason', 'Please enter the reason for the call.')
 *     ->minLength('CallReason', 3, '...');
 *   if ($v->fails()) { $errors = $v->errors(); }
 */
declare(strict_types=1);

final class Validator
{
    private array $errors = [];

    public function __construct(private array $data) {}

    public function value(string $field): string
    {
        $v = $this->data[$field] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    private function fail(string $field, string $message): self
    {
        $this->errors[$field] ??= $message;   // keep the first error per field
        return $this;
    }

    /** Presence check */
    public function required(string $field, string $message): self
    {
        return $this->value($field) === '' ? $this->fail($field, $message) : $this;
    }

    /** Length checks (only when a value is given) */
    public function minLength(string $field, int $min, string $message): self
    {
        $v = $this->value($field);
        return $v !== '' && mb_strlen($v) < $min ? $this->fail($field, $message) : $this;
    }

    public function maxLength(string $field, int $max, string $message): self
    {
        return mb_strlen($this->value($field)) > $max ? $this->fail($field, $message) : $this;
    }

    /** Format (pattern) check */
    public function pattern(string $field, string $regex, string $message): self
    {
        $v = $this->value($field);
        return $v !== '' && !preg_match($regex, $v) ? $this->fail($field, $message) : $this;
    }

    public function email(string $field, string $message): self
    {
        $v = $this->value($field);
        return $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL) ? $this->fail($field, $message) : $this;
    }

    /** Lookup / list check */
    public function inList(string $field, array $allowed, string $message): self
    {
        $v = $this->value($field);
        return $v !== '' && !in_array($v, array_map('strval', $allowed), true) ? $this->fail($field, $message) : $this;
    }

    /** Type + range check for numbers, with a maximum number of decimal places */
    public function number(string $field, float $min, float $max, string $message, int $decimals = 2): self
    {
        $v = $this->value($field);
        if ($v === '') {
            return $this;
        }
        if (!preg_match('/^-?\d+(\.\d{1,' . $decimals . '})?$/', $v) || (float)$v < $min || (float)$v > $max) {
            return $this->fail($field, $message);
        }
        return $this;
    }

    public function integer(string $field, int $min, int $max, string $message): self
    {
        $v = $this->value($field);
        if ($v !== '' && (!ctype_digit($v) || (int)$v < $min || (int)$v > $max)) {
            return $this->fail($field, $message);
        }
        return $this;
    }

    /** Type check for dates (Y-m-d from <input type="date">) */
    public function date(string $field, string $message): self
    {
        $v = $this->value($field);
        $d = DateTime::createFromFormat('!Y-m-d', $v);
        return $v !== '' && (!$d || $d->format('Y-m-d') !== $v) ? $this->fail($field, $message) : $this;
    }

    /** Type check for date-times (from <input type="datetime-local">) */
    public function dateTime(string $field, string $message): self
    {
        $v = $this->value($field);
        return $v !== '' && self::toDateTime($v) === null ? $this->fail($field, $message) : $this;
    }

    public function notFuture(string $field, string $message): self
    {
        $v = $this->value($field);
        $ts = $v === '' ? false : strtotime($v);
        return $ts !== false && $ts > time() ? $this->fail($field, $message) : $this;
    }

    /** Cross-field / business-rule check supplied by the caller */
    public function rule(bool $ok, string $field, string $message): self
    {
        return $ok ? $this : $this->fail($field, $message);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /** "2026-11-02T15:10" -> "2026-11-02 15:10:00" (or null if invalid) */
    public static function toDateTime(string $v): ?string
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $fmt) {
            $d = DateTime::createFromFormat($fmt, $v);
            if ($d && $d->format($fmt) === $v) {
                return $d->format('Y-m-d H:i:s');
            }
        }
        return null;
    }
}
