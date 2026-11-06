<?php
/**
 * One-time installer.
 *
 * Browser: open https://your-domain/install.php and fill in the form.
 * Command line: php install.php --name="Admin" --email=admin@example.com --password=Secret123 [--demo]
 *
 * After a successful install the file storage/install.lock is created and this
 * installer refuses to run again. Delete install.php from the server afterwards.
 */
require __DIR__ . '/api/lib/bootstrap.php';
require __DIR__ . '/api/lib/migrations.php';
require __DIR__ . '/api/lib/http.php';
require __DIR__ . '/api/lib/demo.php';
require __DIR__ . '/api/handlers/features.php';

$cli = PHP_SAPI === 'cli';

function run_install(string $name, string $email, string $password, bool $demo): array
{
    $log = [];
    if (db_driver() === 'sqlite') {
        $path = env('DB_PATH', 'storage/database.sqlite');
        $dir = dirname($path[0] === '/' ? $path : ROOT . '/' . $path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
    run_sql_script(file_get_contents(ROOT . '/database/schema.mysql.sql'));
    $log[] = 'Database tables created';

    $defaults = [
        'clinic_name' => 'Purrfect Pet Care',
        'clinic_phone' => '+254 792 002 230',
        'clinic_email' => 'info@purrfectpetcare.com',
        'clinic_address' => 'Moi Avenue, Nairobi',
        'clinic_hours' => 'Mon-Fri: 8am-7pm, Sat: 9am-4pm',
        'open_time' => '08:00',
        'close_time' => '19:00',
        'closed_days' => '0',
        'session_timeout' => '120',
    ];
    foreach ($defaults as $k => $v) {
        if (setting($k) === null) {
            set_setting($k, $v);
        }
    }

    if (!q_val('SELECT COUNT(*) FROM services')) {
        $services = [
            ['Consultation', 'General consultation with a veterinarian.', 1500, 30],
            ['Vaccination', 'Core and lifestyle vaccines with a health check.', 1500, 30],
            ['Wellness Exam', 'Full physical exam, weight and nutrition assessment, vaccination plan.', 2500, 30],
            ['Feline Wellness', 'Cat-specific check-up, vaccines, nutrition and behaviour advice.', 2500, 30],
            ['Basic Care Package', 'Wellness exam, basic vaccinations and nutrition consultation.', 2500, 45],
            ['Dental Care', 'Dental exam, scaling and polishing. X-rays and extractions quoted separately.', 4000, 60],
            ['Premium Care Package', 'Comprehensive exam, all vaccinations, dental cleaning and parasite prevention.', 4500, 90],
            ['Emergency Care', 'Immediate treatment, diagnostic tests and monitoring.', 7000, 60],
        ];
        foreach ($services as [$n, $d, $p, $m]) {
            insert('services', ['name' => $n, 'description' => $d, 'price' => $p, 'duration_minutes' => $m, 'active' => 1, 'created_at' => now()]);
        }
        $log[] = 'Default services and prices added';
    }

    $email = strtolower(trim($email));
    if (q_val('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) {
        q("UPDATE users SET name = ?, password_hash = ?, role = 'admin', status = 'active' WHERE email = ?", [$name, password_hash($password, PASSWORD_DEFAULT), $email]);
    } else {
        insert('users', ['name' => $name, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'admin', 'status' => 'active', 'created_at' => now()]);
    }
    $log[] = "Administrator account ready: $email";

    run_migrations();
    $log[] = 'Database upgraded to version ' . SCHEMA_VERSION;

    if ($demo && !q_val("SELECT COUNT(*) FROM users WHERE email = 'vet@demo.local'")) {
        seed_all_demo();
        $log[] = 'Demo data added (password for all demo accounts: ' . DEMO_PASSWORD . ')';
    }

    if (!is_dir(ROOT . '/storage')) {
        mkdir(ROOT . '/storage', 0775, true);
    }
    file_put_contents(ROOT . '/storage/install.lock', 'Installed ' . now() . "\n");
    $log[] = 'Installation complete';
    return $log;
}

// ---------------------------------------------------------------------------
if ($cli) {
    if (is_installed() && !in_array('--force', $argv, true)) {
        fwrite(STDERR, "Already installed (storage/install.lock exists).\n");
        exit(1);
    }
    $opts = getopt('', ['name:', 'email:', 'password:', 'demo', 'force']);
    if (empty($opts['email']) || empty($opts['password'])) {
        fwrite(STDERR, "Usage: php install.php --name=\"Admin\" --email=you@example.com --password=Secret123 [--demo]\n");
        exit(1);
    }
    try {
        foreach (run_install($opts['name'] ?? 'Administrator', $opts['email'], $opts['password'], isset($opts['demo'])) as $line) {
            echo "- $line\n";
        }
    } catch (Throwable $e) {
        fwrite(STDERR, 'Install failed: ' . $e->getMessage() . "\n");
        exit(1);
    }
    exit(0);
}

$errors = [];
$log = [];
$checks = [
    'PHP 7.4 or newer' => version_compare(PHP_VERSION, '7.4', '>='),
    'PDO extension' => extension_loaded('pdo'),
    (db_driver() === 'sqlite' ? 'pdo_sqlite' : 'pdo_mysql') . ' extension' => extension_loaded(db_driver() === 'sqlite' ? 'pdo_sqlite' : 'pdo_mysql'),
    'cURL extension (for payments)' => extension_loaded('curl'),
    'mbstring extension' => extension_loaded('mbstring'),
    '.env file present' => is_file(ROOT . '/.env'),
    'storage/ folder writable' => is_dir(ROOT . '/storage') && is_writable(ROOT . '/storage'),
];
$dbOk = false;
try {
    db();
    $dbOk = true;
} catch (Throwable $e) {
    $errors[] = 'Database connection failed: ' . $e->getMessage();
}
$checks['Database connection'] = $dbOk;

if (!is_installed() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter your name and a valid email.';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must be at least 8 characters with letters and numbers.';
    } elseif ($password !== ($_POST['password2'] ?? '')) {
        $errors[] = 'Passwords do not match.';
    } elseif (in_array(false, $checks, true)) {
        $errors[] = 'Fix the failed checks above first.';
    } else {
        try {
            $log = run_install($name, $email, $password, !empty($_POST['demo']));
        } catch (Throwable $e) {
            $errors[] = 'Install failed: ' . $e->getMessage();
        }
    }
}
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="assets/img/paw-favicon.png?v=1">
<title>Install - Purrfect Pet Care</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#FFF5E1;font-family:system-ui,sans-serif}.card{max-width:640px;margin:40px auto;border:0;box-shadow:0 8px 30px rgba(0,0,0,.08)}.btn-brand{background:#FF7300;color:#fff}.btn-brand:hover{background:#e56700;color:#fff}</style>
</head>
<body>
<div class="card"><div class="card-body p-4">
<h1 class="h3 mb-3">Purrfect Pet Care installer</h1>
<?php if (is_installed() && !$log): ?>
  <div class="alert alert-success">The system is already installed. <strong>Delete install.php from your server</strong> for safety.</div>
  <a class="btn btn-brand" href="index.html">Go to the website</a>
<?php elseif ($log): ?>
  <div class="alert alert-success"><ul class="mb-0"><?php foreach ($log as $l) echo '<li>' . $h($l) . '</li>'; ?></ul></div>
  <p><strong>Next:</strong> delete <code>install.php</code> from your server, then log in with the admin account you just created.</p>
  <a class="btn btn-brand" href="index.html">Go to the website</a>
<?php else: ?>
  <h2 class="h6 text-uppercase text-muted">System checks</h2>
  <ul class="list-group mb-3">
  <?php foreach ($checks as $label => $ok): ?>
    <li class="list-group-item d-flex justify-content-between"><?= $h($label) ?><span class="badge bg-<?= $ok ? 'success' : 'danger' ?>"><?= $ok ? 'OK' : 'Failed' ?></span></li>
  <?php endforeach; ?>
  </ul>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= $h($e) ?></div><?php endforeach; ?>
  <form method="post">
    <h2 class="h6 text-uppercase text-muted">Administrator account</h2>
    <div class="mb-2"><label class="form-label">Full name</label><input class="form-control" name="name" required value="<?= $h($_POST['name'] ?? '') ?>"></div>
    <div class="mb-2"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required value="<?= $h($_POST['email'] ?? '') ?>"></div>
    <div class="row g-2 mb-2">
      <div class="col"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required minlength="8"></div>
      <div class="col"><label class="form-label">Confirm password</label><input class="form-control" type="password" name="password2" required minlength="8"></div>
    </div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="demo" id="demo" value="1"><label class="form-check-label" for="demo">Add demo data (sample vets, clients, pets and appointments; password <code>Demo1234</code>)</label></div>
    <button class="btn btn-brand w-100">Install</button>
  </form>
<?php endif; ?>
</div></div>
</body>
</html>
