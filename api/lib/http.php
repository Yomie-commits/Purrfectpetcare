<?php
/**
 * JSON API helpers: request input, responses and validation.
 */
defined('APP_LOADED') or exit;

class ApiError extends Exception
{
    public int $status;

    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function fail(string $message, int $status = 400): void
{
    throw new ApiError($message, $status);
}

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input(): array
{
    static $in = null;
    if ($in !== null) {
        return $in;
    }
    $in = $_GET;
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '[]', true);
        if (is_array($json)) {
            $in = array_merge($in, $json);
        }
    } else {
        $in = array_merge($in, $_POST);
    }
    return $in;
}

/** Optional string input (trimmed). Returns $default when missing/empty. */
function opt(string $key, ?string $default = null): ?string
{
    $v = input()[$key] ?? null;
    if ($v === null || is_array($v)) {
        return $default;
    }
    $v = trim((string)$v);
    return $v === '' ? $default : $v;
}

/** Required string input. */
function req(string $key, ?string $label = null, int $max = 5000): string
{
    $v = opt($key);
    if ($v === null) {
        fail(($label ?? ucfirst(str_replace('_', ' ', $key))) . ' is required');
    }
    if (mb_strlen($v) > $max) {
        fail(($label ?? $key) . " must be at most $max characters");
    }
    return $v;
}

function req_id(string $key = 'id'): int
{
    $v = input()[$key] ?? null;
    if ($v === null || !preg_match('/^\d+$/', (string)$v) || (int)$v < 1) {
        fail("A valid $key is required");
    }
    return (int)$v;
}

function opt_id(string $key): ?int
{
    $v = input()[$key] ?? null;
    if ($v === null || $v === '') {
        return null;
    }
    if (!preg_match('/^\d+$/', (string)$v)) {
        fail("Invalid $key");
    }
    return (int)$v;
}

function v_enum(?string $value, array $allowed, string $label)
{
    if ($value === null || !in_array($value, $allowed, true)) {
        fail("Invalid $label. Allowed: " . implode(', ', $allowed));
    }
    return $value;
}

function v_email(string $email): string
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        fail('Please enter a valid email address');
    }
    return $email;
}

function v_date(?string $d, string $label = 'date', bool $required = true): ?string
{
    if ($d === null || $d === '') {
        if ($required) {
            fail("$label is required");
        }
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    if (!$dt || $dt->format('Y-m-d') !== $d) {
        fail("Invalid $label (use YYYY-MM-DD)");
    }
    return $d;
}

function v_time(?string $t): string
{
    if ($t === null || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $t)) {
        fail('Invalid time (use HH:MM)');
    }
    return substr($t, 0, 5) . ':00';
}

function v_money(?string $v, string $label = 'amount', bool $allowZero = false): float
{
    if ($v === null || !is_numeric($v)) {
        fail("A valid $label is required");
    }
    $f = round((float)$v, 2);
    if ($f < 0 || (!$allowZero && $f <= 0) || $f > 10000000) {
        fail("Invalid $label");
    }
    return $f;
}

function v_decimal(?string $v, string $label): ?float
{
    if ($v === null || $v === '') {
        return null;
    }
    if (!is_numeric($v) || (float)$v < 0 || (float)$v > 100000) {
        fail("Invalid $label");
    }
    return round((float)$v, 2);
}

function v_password(string $p): string
{
    if (strlen($p) < 8) {
        fail('Password must be at least 8 characters');
    }
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) {
        fail('Password must contain letters and numbers');
    }
    return $p;
}

function v_phone(?string $p, bool $required = false): ?string
{
    if ($p === null || $p === '') {
        if ($required) {
            fail('Phone number is required');
        }
        return null;
    }
    $clean = preg_replace('/[\s\-()]/', '', $p);
    if (!preg_match('/^\+?\d{9,15}$/', $clean)) {
        fail('Please enter a valid phone number');
    }
    return $clean;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        fail('Please log in to continue', 401);
    }
    return $u;
}

function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        fail('You do not have permission to do that', 403);
    }
    return $u;
}

/** Everyone who works at the clinic (not pet parents). */
const STAFF_ROLES = ['vet', 'admin', 'reception', 'assistant'];

/** What each staff role may do. Admins may do everything. */
const ROLE_PERMS = [
    'medical.view'        => ['vet', 'assistant'],              // diagnoses, treatment notes, records
    'medical.write'       => ['vet'],                           // add / edit health records
    'treatment.write'     => ['vet', 'assistant'],              // vaccinations, deworming, flea & tick
    'appointments.manage' => ['vet', 'reception'],              // confirm, reschedule, assign, walk-ins
    'checkin'             => ['vet', 'reception', 'assistant'], // mark arrived / with vet
    'billing'             => ['vet', 'reception'],              // invoice lines, discounts, payments at the desk
    'stock.manage'        => ['vet', 'assistant'],              // add items, adjust stock
    'community.manage'    => ['vet', 'reception'],              // adoption listings and requests
];

const ROLE_LABELS = ['owner' => 'pet parent', 'vet' => 'veterinarian', 'admin' => 'administrator', 'reception' => 'receptionist', 'assistant' => 'vet assistant'];

function is_staff(array $u): bool
{
    return in_array($u['role'], STAFF_ROLES, true);
}

function can(array $u, string $perm): bool
{
    return $u['role'] === 'admin' || in_array($u['role'], ROLE_PERMS[$perm] ?? [], true);
}

function require_perm(string $perm): array
{
    $u = require_login();
    if (!can($u, $perm)) {
        fail('You do not have permission to do that', 403);
    }
    return $u;
}

function require_staff(): array
{
    $u = require_login();
    if (!is_staff($u)) {
        fail('You do not have permission to do that', 403);
    }
    return $u;
}

/** Permissions for the current user, sent to the dashboard so it can hide buttons. */
function user_perms(array $u): array
{
    $out = [];
    foreach (array_keys(ROLE_PERMS) as $p) {
        if (can($u, $p)) {
            $out[] = $p;
        }
    }
    return $out;
}

function check_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        fail('Your session has expired. Please refresh the page.', 419);
    }
}
