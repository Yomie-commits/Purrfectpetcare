<?php
/**
 * Core bootstrap: configuration, database, session and shared helpers.
 * Every PHP entry point includes this file first.
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__, 2));
define('APP_LOADED', true);

// ---------------------------------------------------------------------------
// Configuration (.env)
// ---------------------------------------------------------------------------
function load_env(string $file): void
{
    if (!is_file($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
            $v = substr($v, 1, -1);
        }
        $_ENV[$k] = $v;
    }
}
load_env(ROOT . '/.env');

function env(string $key, ?string $default = null): ?string
{
    $v = $_ENV[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : (string)$v;
}

date_default_timezone_set(env('APP_TIMEZONE', 'Africa/Nairobi'));
error_reporting(E_ALL);
ini_set('display_errors', env('APP_DEBUG') === 'true' ? '1' : '0');
ini_set('log_errors', '1');
if (is_dir(ROOT . '/storage') && is_writable(ROOT . '/storage')) {
    ini_set('error_log', ROOT . '/storage/php-errors.log');
}

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------
function db_driver(): string
{
    return env('DB_DRIVER', 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (db_driver() === 'sqlite') {
        $path = env('DB_PATH', 'storage/database.sqlite');
        if ($path[0] !== '/') {
            $path = ROOT . '/' . $path;
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', 'localhost'),
            env('DB_PORT', '3306'),
            env('DB_NAME', '')
        );
        $pdo = new PDO($dsn, env('DB_USER', ''), env('DB_PASS', ''), $opts);
        // Keep MySQL's clock in step with PHP's timezone.
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    }
    return $pdo;
}

/** Run a query with parameters and return the statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function q_val(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(', ', $cols),
        implode(', ', array_fill(0, count($cols), '?'))
    );
    q($sql, array_values($data));
    return (int)db()->lastInsertId();
}

function update(string $table, array $data, int $id): void
{
    if (!$data) {
        return;
    }
    $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
    $params = array_values($data);
    $params[] = $id;
    q("UPDATE $table SET $set WHERE id = ?", $params);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------
/** All settings, loaded once per request and kept in step by set_setting(). */
function &settings_cache(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (q_all('SELECT setting_key, setting_value FROM settings') as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Throwable $e) {
            // settings table may not exist yet during installation
            $cache = null;
            $empty = [];
            return $empty;
        }
    }
    return $cache;
}

function setting(string $key, ?string $default = null): ?string
{
    $c = &settings_cache();
    return $c[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    $exists = q_val('SELECT COUNT(*) FROM settings WHERE setting_key = ?', [$key]);
    if ($exists) {
        q('UPDATE settings SET setting_value = ? WHERE setting_key = ?', [$value, $key]);
    } else {
        q('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)', [$key, $value]);
    }
    $c = &settings_cache();
    $c[$key] = $value;
}

// ---------------------------------------------------------------------------
// Sessions & URLs
// ---------------------------------------------------------------------------
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('PPCSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Idle timeout (minutes) configurable by the admin.
    $timeout = max(5, (int)setting('session_timeout', '120')) * 60;
    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > $timeout) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

/** Absolute base URL of the app, e.g. https://example.com/petcare */
function app_url(): string
{
    $u = env('APP_URL');
    if ($u) {
        return rtrim($u, '/');
    }
    $scheme = is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $dir = preg_replace('#/api$#', '', $dir);
    return "$scheme://$host$dir";
}

// ---------------------------------------------------------------------------
// Users / auth helpers
// ---------------------------------------------------------------------------
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $u = q_one('SELECT id, name, email, phone, address, emergency_name, emergency_phone, role, status, specialization, created_at FROM users WHERE id = ?', [$_SESSION['uid']]);
        if ($u && $u['status'] === 'active') {
            $u['id'] = (int)$u['id'];
            $user = $u;
        } else {
            unset($_SESSION['uid']);
        }
    }
    return $user;
}

function public_user(array $u): array
{
    unset($u['password_hash']);
    $u['id'] = (int)$u['id'];
    return $u;
}

function notify(int $userId, string $title, string $message = '', string $link = ''): void
{
    insert('notifications', [
        'user_id' => $userId,
        'title' => mb_substr($title, 0, 150),
        'message' => $message,
        'link' => $link,
        'is_read' => 0,
        'created_at' => now(),
    ]);
}

function notify_role(string $role, string $title, string $message = '', string $link = ''): void
{
    foreach (q_all("SELECT id FROM users WHERE role = ? AND status = 'active'", [$role]) as $r) {
        notify((int)$r['id'], $title, $message, $link);
    }
}

/**
 * Send an email if MAIL_ENABLED=true. Uses PHP's mail(), which works on
 * most cPanel hosts. Returns false (and logs) when mail is disabled/fails.
 */
function send_mail(string $to, string $subject, string $body): bool
{
    if (env('BREVO_API_KEY')) {
        // Brevo sends over HTTPS, which works on hosts that block PHP mail().
        require_once __DIR__ . '/messaging.php';
        return email_send($to, $subject, $body)['status'] === 'sent';
    }
    if (env('MAIL_ENABLED') !== 'true') {
        error_log("[mail disabled] To: $to | $subject");
        return false;
    }
    $from = env('MAIL_FROM', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $name = setting('clinic_name', 'Purrfect Pet Care');
    $headers = [
        'From: ' . sprintf('"%s" <%s>', addslashes($name), $from),
        'Reply-To: ' . setting('clinic_email', $from),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
    if (!$ok) {
        error_log("[mail failed] To: $to | $subject");
    }
    return $ok;
}

function money(float $v): string
{
    return 'KSh ' . number_format($v, 2);
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function is_installed(): bool
{
    return is_file(ROOT . '/storage/install.lock');
}
