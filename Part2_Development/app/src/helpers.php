<?php
/**
 * Small helper functions used by pages and templates.
 */
declare(strict_types=1);

/** Escape text for safe HTML output (prevents XSS). */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $key): mixed
{
    return $GLOBALS['config'][$key] ?? null;
}

/** Build a URL relative to the public/ folder, e.g. url('calls/new.php'). */
function url(string $path = '', array $query = []): string
{
    $u = rtrim((string)config('base_url'), '/') . '/' . ltrim($path, '/');
    return $query ? $u . '?' . http_build_query($query) : $u;
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query));
    exit;
}

/** One-time messages shown on the next page (success / error / info). */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Trimmed request value (POST first, then GET). */
function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/** P-000128 style problem reference. */
function problem_ref(int|string|null $n): string
{
    return $n === null || $n === '' ? '' : 'P-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
}

/** Accepts "P-000128", "p128" or "128"; returns 128 or null. */
function parse_problem_ref(string $s): ?int
{
    return preg_match('/^\s*(?:P-?)?0*(\d{1,9})\s*$/i', $s, $m) ? (int)$m[1] : null;
}

function fmt_date(?string $d): string
{
    return $d ? date('d/m/Y', strtotime($d)) : '';
}

function fmt_datetime(?string $d): string
{
    return $d ? date('d/m/Y H:i', strtotime($d)) : '';
}

function fmt_hours(mixed $h): string
{
    return $h === null || $h === '' ? '—' : number_format((float)$h, 2);
}

/** Coloured status badge used for warranty, licence and problem status. */
function badge(string $text): string
{
    $good = ['Under warranty', 'Valid', 'Closed', 'Yes', 'Success', 'Current', 'OK', 'Active'];
    $bad  = ['Expired', 'No valid licence', 'Failed', 'Locked', 'Critical', 'Left',
             'Consider replacing', 'Renew licence', 'Regularly failing - review'];
    $cls  = in_array($text, $good, true) ? 'badge-good'
          : (in_array($text, $bad, true) || str_starts_with($text, 'Left') ? 'badge-bad' : 'badge-info');
    return '<span class="badge ' . $cls . '">' . e($text) . '</span>';
}

/** <option> list; $items = [value => label]. */
function options(array $items, string|int|null $selected = null, string $placeholder = ''): string
{
    $html = $placeholder !== '' ? '<option value="">' . e($placeholder) . '</option>' : '';
    foreach ($items as $value => $label) {
        $sel = ((string)$value === (string)$selected) ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    return $html;
}

/** Shows the validation error under a form field. */
function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<div class="field-error">' . e($errors[$field]) . '</div>' : '';
}

/**
 * One labelled form field with its validation error.
 * $type: text | email | date | number | select | textarea
 * $attrs: extra HTML attributes, e.g. ['maxlength' => 60, 'required' => true]
 */
function form_field(string $name, string $label, mixed $value, array $errors,
                    string $type = 'text', array $attrs = [], array $choices = []): string
{
    $required = !empty($attrs['required']);
    $attr = '';
    foreach ($attrs as $k => $v) {
        if ($v === true) { $attr .= ' ' . $k; }
        elseif ($v !== false && $v !== null) { $attr .= ' ' . $k . '="' . e($v) . '"'; }
    }
    $id = 'f_' . $name;
    $html = '<div' . (isset($errors[$name]) ? ' class="has-error"' : '') . '>'
          . '<label for="' . $id . '">' . e($label) . ($required ? ' <span class="req">*</span>' : '') . '</label>';
    if ($type === 'select') {
        $html .= '<select id="' . $id . '" name="' . e($name) . '"' . $attr . '>'
               . options($choices, $value, $required ? '- choose -' : '- none -') . '</select>';
    } elseif ($type === 'textarea') {
        $html .= '<textarea id="' . $id . '" name="' . e($name) . '"' . $attr . '>' . e($value) . '</textarea>';
    } else {
        $html .= '<input id="' . $id . '" name="' . e($name) . '" type="' . e($type) . '" value="' . e($value) . '"' . $attr . '>';
    }
    return $html . field_error($errors, $name) . '</div>';
}

/** Empty string -> NULL, for optional database columns. */
function nullable(string $v): ?string
{
    return $v === '' ? null : $v;
}

/** Render a page template with variables. */
function render(string $template, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require APP_ROOT . '/templates/' . $template . '.php';
}
