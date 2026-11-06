<?php
/** Dashboard stats, users, services, settings, reports and notifications. */
defined('APP_LOADED') or exit;

const SERVICE_CATEGORIES = ['consultation', 'vaccination', 'checkup', 'dental', 'surgery', 'grooming', 'emergency', 'other'];

const EDITABLE_SETTINGS = [
    'clinic_name', 'clinic_phone', 'clinic_email', 'clinic_address', 'clinic_hours',
    'open_time', 'close_time', 'closed_days', 'session_timeout',
];

function dashboard_stats(): array
{
    $u = require_login();
    $today = today();
    $monthStart = date('Y-m-01');
    $soon = date('Y-m-d', strtotime('+30 days'));
    $out = ['role' => $u['role']];

    if ($u['role'] === 'owner') {
        $id = $u['id'];
        $out['pets'] = (int)q_val("SELECT COUNT(*) FROM pets WHERE owner_id = ? AND status = 'active'", [$id]);
        $out['upcoming'] = (int)q_val("SELECT COUNT(*) FROM appointments WHERE owner_id = ? AND status IN ('pending','confirmed') AND appt_date >= ?", [$id, $today]);
        $out['vaccines_due'] = count(array_filter(
            q_all("SELECT v.pet_id, v.vaccine, v.next_due FROM vaccinations v JOIN pets p ON p.id = v.pet_id
                   WHERE p.owner_id = ? AND p.status = 'active' AND v.next_due IS NOT NULL AND v.next_due <= ?
                   AND NOT EXISTS (SELECT 1 FROM vaccinations v2 WHERE v2.pet_id = v.pet_id AND v2.vaccine = v.vaccine AND v2.date_given > v.date_given)", [$id, $soon])
        ));
        $bal = 0.0;
        foreach (q_all(appointment_select() . " WHERE a.owner_id = ? AND a.status NOT IN ('cancelled','no_show')", [$id]) as $a) {
            $bal += appointment_decorate($a)['balance'];
        }
        $out['balance'] = round($bal, 2);
        $out['next_appointments'] = array_map('appointment_decorate', q_all(appointment_select() .
            " WHERE a.owner_id = ? AND a.status IN ('pending','confirmed') AND a.appt_date >= ? ORDER BY a.appt_date, a.appt_time LIMIT 5", [$id, $today]));
    } else {
        $out['today_appointments'] = array_map('appointment_decorate', q_all(appointment_select() .
            " WHERE a.appt_date = ? AND a.status NOT IN ('cancelled')" . ($u['role'] === 'vet' ? ' AND (a.vet_id = ? OR a.vet_id IS NULL)' : '') .
            ' ORDER BY a.appt_time', $u['role'] === 'vet' ? [$today, $u['id']] : [$today]));
        $out['pending_requests'] = (int)q_val("SELECT COUNT(*) FROM appointments WHERE status = 'pending' AND appt_date >= ?", [$today]);
        $out['vaccines_due'] = (int)q_val("SELECT COUNT(*) FROM vaccinations v JOIN pets p ON p.id = v.pet_id WHERE p.status = 'active' AND v.next_due IS NOT NULL AND v.next_due <= ?
            AND NOT EXISTS (SELECT 1 FROM vaccinations v2 WHERE v2.pet_id = v.pet_id AND v2.vaccine = v.vaccine AND v2.date_given > v.date_given)", [$soon]);
        $out['patients'] = (int)q_val("SELECT COUNT(*) FROM pets WHERE status = 'active'");
        $out['adoption_requests'] = (int)q_val("SELECT COUNT(*) FROM adoption_requests WHERE status = 'pending'");
        $out['low_stock'] = (int)q_val('SELECT COUNT(*) FROM products WHERE active = 1 AND stock_qty <= reorder_level');
        $out['waiting'] = (int)q_val("SELECT COUNT(*) FROM appointments WHERE appt_date = ? AND status = 'checked_in'", [$today]);
        if (can($u, 'billing')) {
            $unpaid = array_map('appointment_decorate', q_all(appointment_select() . " WHERE a.status = 'completed' AND a.price > 0"));
            $unpaid = array_filter($unpaid, fn($a) => $a['balance'] > 0);
            $out['unpaid_count'] = count($unpaid);
            $out['unpaid_total'] = round(array_sum(array_column($unpaid, 'balance')), 2);
        }
        if ($u['role'] === 'admin') {
            $out['users'] = (int)q_val('SELECT COUNT(*) FROM users');
            $out['owners'] = (int)q_val("SELECT COUNT(*) FROM users WHERE role = 'owner'");
            $out['vets'] = (int)q_val("SELECT COUNT(*) FROM users WHERE role = 'vet' AND status = 'active'");
            $out['revenue_month'] = (float)q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'completed' AND paid_at >= ?", [$monthStart . ' 00:00:00']);
            $out['revenue_total'] = (float)q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'completed'");
            $out['pending_posts'] = (int)q_val("SELECT COUNT(*) FROM forum_posts WHERE status = 'pending'");
            $out['pending_users'] = (int)q_val("SELECT COUNT(*) FROM users WHERE status = 'pending'");
            $out['demo_users'] = count(demo_user_ids());
            $last = setting('last_backup_at');
            $out['days_since_backup'] = $last ? (int)floor((time() - strtotime($last)) / 86400) : null;
            $out['recent_payments'] = array_slice(payments_list(), 0, 6);
        }
    }
    return $out;
}

function vets_list(): array
{
    require_login();
    return q_all("SELECT id, name, specialization FROM users WHERE role = 'vet' AND status = 'active' ORDER BY name");
}

function users_list(): array
{
    $u = require_staff();
    $sql = 'SELECT id, name, email, phone, address, emergency_name, emergency_phone, role, status, specialization, last_login, created_at,
                   (SELECT COUNT(*) FROM pets p WHERE p.owner_id = users.id) AS pet_count
            FROM users WHERE 1=1';
    $params = [];
    if ($u['role'] !== 'admin') {
        $sql .= " AND role = 'owner'"; // staff may look up clients only
    } else {
        if ($r = opt('role')) {
            $sql .= ' AND role = ?';
            $params[] = v_enum($r, array_keys(ROLE_LABELS), 'role');
        }
        if ($st = opt('status')) {
            $sql .= ' AND status = ?';
            $params[] = v_enum($st, ['active', 'inactive', 'pending'], 'status');
        }
    }
    if ($s = opt('q')) {
        $sql .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)';
        array_push($params, "%$s%", "%$s%", "%$s%");
    }
    $rows = q_all($sql . ' ORDER BY name LIMIT 1000', $params);
    if (can($u, 'billing')) {
        foreach ($rows as &$r) {
            $r['balance'] = $r['role'] === 'owner' ? owner_balance((int)$r['id']) : 0;
        }
    }
    return $rows;
}

function user_save(): array
{
    $me = require_login();
    // Admins manage every account; front-desk staff may add and edit pet parents only.
    $clientOnly = $me['role'] !== 'admin';
    if ($clientOnly && !can($me, 'appointments.manage')) {
        fail('You do not have permission to do that', 403);
    }
    $id = opt_id('id');
    if ($clientOnly && $id && q_val('SELECT role FROM users WHERE id = ?', [$id]) !== 'owner') {
        fail('You can only edit pet parent accounts', 403);
    }
    $data = [
        'name' => req('name', 'Name', 120),
        'email' => user_email_or_placeholder($id),
        'phone' => v_phone(opt('phone')),
        'address' => opt('address'),
        'role' => v_enum(opt('role', 'owner'), array_keys(ROLE_LABELS), 'role'),
        'emergency_name' => opt('emergency_name'),
        'emergency_phone' => v_phone(opt('emergency_phone')),
        'status' => v_enum(opt('status', 'active'), ['active', 'inactive', 'pending'], 'status'),
        'specialization' => opt('specialization'),
    ];
    if ($clientOnly) {
        $data['role'] = 'owner';
        $data['status'] = $id ? q_val('SELECT status FROM users WHERE id = ?', [$id]) : 'active';
        $data['specialization'] = null;
    }
    if (q_val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$data['email'], $id ?? 0])) {
        fail('Another account already uses that email');
    }
    $password = opt('password');
    if ($id) {
        $existing = q_one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$existing) {
            fail('User not found', 404);
        }
        if ($id === $me['id'] && ($data['role'] !== 'admin' || $data['status'] !== 'active')) {
            fail('You cannot remove your own admin access or deactivate yourself');
        }
        if ($password && !$clientOnly) {
            $data['password_hash'] = password_hash(v_password($password), PASSWORD_DEFAULT);
        }
        update('users', $data, $id);
        if ($existing['status'] === 'pending' && $data['status'] === 'active') {
            notify($id, 'Your account was approved', 'Welcome to the team! You now have ' . ROLE_LABELS[$data['role']] . ' access.', 'overview');
            send_mail($data['email'], 'Your account was approved', "Hi {$data['name']},\n\nYour account has been approved. Log in at " . app_url() . "\n");
        }
    } else {
        // Front desk can create a client without a password; they set one with "Forgot password".
        $data['password_hash'] = password_hash(($clientOnly && !$password) ? bin2hex(random_bytes(16)) : v_password($password ?? ''), PASSWORD_DEFAULT);
        $data['created_at'] = now();
        $id = insert('users', $data);
        notify($id, 'Welcome to ' . setting('clinic_name', 'Purrfect Pet Care'), 'Your account was created by the clinic.', 'profile');
    }
    return q_one('SELECT id, name, email, phone, address, emergency_name, emergency_phone, role, status, specialization, created_at FROM users WHERE id = ?', [$id]);
}

/** Pet parents may have no email (walk-ins): they get a placeholder address that is never shown. */
function user_email_or_placeholder(?int $id): string
{
    $email = trim((string)opt('email'));
    if ($email !== '') {
        return v_email($email);
    }
    if (opt('role', 'owner') !== 'owner' && current_user()['role'] === 'admin') {
        fail('Email is required for staff accounts');
    }
    $existing = $id ? (string)q_val('SELECT email FROM users WHERE id = ?', [$id]) : '';
    if ($existing && substr($existing, -13) === '@walkin.local') {
        return $existing;
    }
    if ($existing) {
        fail('Please enter an email address');
    }
    return 'walkin-' . preg_replace('/\D/', '', (string)opt('phone')) . '-' . bin2hex(random_bytes(3)) . '@walkin.local';
}

function user_delete(): array
{
    $me = require_role('admin');
    $id = req_id();
    if ($id === $me['id']) {
        fail('You cannot delete your own account here. Use your profile page.');
    }
    if (!q_val('SELECT COUNT(*) FROM users WHERE id = ?', [$id])) {
        fail('User not found', 404);
    }
    purge_user_files($id);
    q('DELETE FROM users WHERE id = ?', [$id]);
    return ['deleted' => $id];
}

function services_list(): array
{
    $u = require_login();
    $sql = 'SELECT s.*, (SELECT COUNT(*) FROM appointments a WHERE a.service_id = s.id) AS bookings FROM services s';
    if (!is_staff($u)) {
        $sql .= ' WHERE s.active = 1';
    }
    return q_all($sql . ' ORDER BY s.active DESC, s.name');
}

function service_save(): array
{
    require_role('admin');
    $id = opt_id('id');
    $data = [
        'name' => req('name', 'Service name', 120),
        'description' => opt('description'),
        'price' => v_money(opt('price'), 'price', true),
        'duration_minutes' => max(10, min(480, (int)(opt('duration_minutes') ?? 30))),
        'category' => v_enum(opt('category', 'consultation'), SERVICE_CATEGORIES, 'category'),
        'active' => opt('active', '1') === '1' ? 1 : 0,
    ];
    if ($id) {
        if (!q_val('SELECT COUNT(*) FROM services WHERE id = ?', [$id])) {
            fail('Service not found', 404);
        }
        update('services', $data, $id);
    } else {
        $data['created_at'] = now();
        $id = insert('services', $data);
    }
    return q_one('SELECT * FROM services WHERE id = ?', [$id]);
}

function service_delete(): array
{
    require_role('admin');
    $id = req_id();
    if (q_val('SELECT COUNT(*) FROM appointments WHERE service_id = ?', [$id])) {
        q('UPDATE services SET active = 0 WHERE id = ?', [$id]);
        return ['deactivated' => $id, 'message' => 'This service has bookings, so it was hidden instead of deleted.'];
    }
    q('DELETE FROM services WHERE id = ?', [$id]);
    return ['deleted' => $id];
}

function settings_get(): array
{
    require_role('admin');
    $out = [];
    foreach (EDITABLE_SETTINGS as $k) {
        $out[$k] = setting($k, '');
    }
    $out['_payments'] = ['mpesa' => mpesa_mode(), 'card' => paystack_mode(), 'mail' => env('MAIL_ENABLED') === 'true', 'email' => email_provider(), 'sms' => sms_provider()];
    $out['_backup'] = ['last' => setting('last_backup_at'), 'zip' => class_exists('ZipArchive')];
    $out['_demo_users'] = count(demo_user_ids());
    $out['_demo_products'] = count(demo_product_ids());
    return $out;
}

function settings_save(): array
{
    require_role('admin');
    $in = input();
    $open = substr(v_time((string)($in['open_time'] ?? setting('open_time', '08:00'))), 0, 5);
    $close = substr(v_time((string)($in['close_time'] ?? setting('close_time', '19:00'))), 0, 5);
    if ($open >= $close) {
        fail('Closing time must be after opening time');
    }
    foreach (EDITABLE_SETTINGS as $k) {
        if (!array_key_exists($k, $in)) {
            continue;
        }
        $v = trim((string)$in[$k]);
        if (in_array($k, ['open_time', 'close_time'], true)) {
            $v = substr(v_time($v), 0, 5);
        } elseif ($k === 'session_timeout') {
            $v = (string)max(5, min(1440, (int)$v));
        } elseif ($k === 'closed_days') {
            $v = implode(',', array_unique(array_filter(explode(',', $v), fn($d) => preg_match('/^[0-6]$/', $d))));
        } elseif ($k === 'clinic_email' && $v !== '') {
            $v = v_email($v);
        } elseif ($k === 'clinic_name' && $v === '') {
            fail('Clinic name is required');
        }
        set_setting($k, mb_substr($v, 0, 500));
    }
    return ['message' => 'Settings saved'];
}

function reports(): array
{
    require_role('admin');
    $from = v_date(opt('from', date('Y-m-d', strtotime('-11 months', strtotime(date('Y-m-01'))))), 'from date');
    $to = v_date(opt('to', today()), 'to date');
    $toEnd = $to . ' 23:59:59';
    $revenue = q_all("SELECT SUBSTR(paid_at, 1, 7) AS month, SUM(amount) AS total, COUNT(*) AS count
                      FROM payments WHERE status = 'completed' AND paid_at BETWEEN ? AND ? GROUP BY SUBSTR(paid_at, 1, 7) ORDER BY month", [$from, $toEnd]);
    $byMethod = q_all("SELECT method, SUM(amount) AS total, COUNT(*) AS count FROM payments WHERE status = 'completed' AND paid_at BETWEEN ? AND ? GROUP BY method", [$from, $toEnd]);
    $byService = q_all("SELECT COALESCE(s.name, 'Other') AS service, COUNT(*) AS count, SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed
                        FROM appointments a LEFT JOIN services s ON s.id = a.service_id WHERE a.appt_date BETWEEN ? AND ? GROUP BY s.name ORDER BY count DESC", [$from, $to]);
    $byStatus = q_all('SELECT status, COUNT(*) AS count FROM appointments WHERE appt_date BETWEEN ? AND ? GROUP BY status', [$from, $to]);
    $byVet = q_all("SELECT COALESCE(v.name, 'Unassigned') AS vet, COUNT(*) AS count FROM appointments a LEFT JOIN users v ON v.id = a.vet_id
                    WHERE a.appt_date BETWEEN ? AND ? AND a.status = 'completed' GROUP BY v.name ORDER BY count DESC", [$from, $to]);
    $newClients = (int)q_val("SELECT COUNT(*) FROM users WHERE role = 'owner' AND created_at BETWEEN ? AND ?", [$from, $toEnd]);
    $species = q_all("SELECT species, COUNT(*) AS count FROM pets WHERE status = 'active' GROUP BY species ORDER BY count DESC");

    // Vaccination compliance: active dogs and cats whose latest dose of every vaccine is not overdue.
    $pets = q_all("SELECT p.id, (SELECT COUNT(*) FROM vaccinations v WHERE v.pet_id = p.id AND COALESCE(v.kind, 'vaccine') = 'vaccine') AS doses,
                          (SELECT COUNT(*) FROM vaccinations v WHERE v.pet_id = p.id AND COALESCE(v.kind, 'vaccine') = 'vaccine' AND v.next_due IS NOT NULL AND v.next_due < ?
                             AND NOT EXISTS (SELECT 1 FROM vaccinations v2 WHERE v2.pet_id = v.pet_id AND v2.vaccine = v.vaccine AND v2.date_given > v.date_given)) AS overdue
                   FROM pets p WHERE p.status = 'active' AND p.species IN ('Dog', 'Cat')", [today()]);
    $compliance = [
        'pets' => count($pets),
        'up_to_date' => count(array_filter($pets, fn($p) => $p['doses'] > 0 && !$p['overdue'])),
        'overdue' => count(array_filter($pets, fn($p) => $p['overdue'] > 0)),
        'never' => count(array_filter($pets, fn($p) => !$p['doses'])),
    ];
    // Client retention: of pet parents seen in the period, how many came more than once.
    $visits = q_all("SELECT owner_id, COUNT(*) AS n FROM appointments WHERE status = 'completed' AND appt_date BETWEEN ? AND ? GROUP BY owner_id", [$from, $to]);
    $retention = ['clients' => count($visits), 'returning' => count(array_filter($visits, fn($v) => $v['n'] > 1))];
    $unpaid = array_filter(array_map('appointment_decorate', q_all(appointment_select() . " WHERE a.status = 'completed' AND a.price > 0")), fn($a) => $a['balance'] > 0);
    $outstanding = ['count' => count($unpaid), 'total' => round(array_sum(array_column($unpaid, 'balance')), 2)];
    $byCategory = q_all("SELECT COALESCE(s.category, 'other') AS category, COUNT(*) AS count FROM appointments a LEFT JOIN services s ON s.id = a.service_id
                         WHERE a.appt_date BETWEEN ? AND ? AND a.status <> 'cancelled' GROUP BY s.category ORDER BY count DESC", [$from, $to]);
    return compact('from', 'to', 'revenue', 'byMethod', 'byService', 'byStatus', 'byVet', 'newClients', 'species', 'compliance', 'retention', 'outstanding', 'byCategory');
}

function report_export(): void
{
    require_role('admin');
    $type = v_enum(opt('type'), ['payments', 'appointments', 'clients', 'pets'], 'report type');
    $from = v_date(opt('from', '2000-01-01'), 'from date');
    $to = v_date(opt('to', today()), 'to date');
    switch ($type) {
        case 'payments':
            $rows = q_all("SELECT pay.reference, pay.created_at, pay.paid_at, u.name AS client, pay.description, pay.method, pay.status, pay.amount, pay.provider_ref
                           FROM payments pay JOIN users u ON u.id = pay.user_id WHERE pay.created_at BETWEEN ? AND ? ORDER BY pay.created_at", [$from, $to . ' 23:59:59']);
            break;
        case 'appointments':
            $rows = q_all('SELECT a.id, a.appt_date, a.appt_time, p.name AS pet, o.name AS owner, v.name AS vet, s.name AS service, a.status, a.price
                           FROM appointments a JOIN pets p ON p.id = a.pet_id JOIN users o ON o.id = a.owner_id LEFT JOIN users v ON v.id = a.vet_id
                           LEFT JOIN services s ON s.id = a.service_id WHERE a.appt_date BETWEEN ? AND ? ORDER BY a.appt_date, a.appt_time', [$from, $to]);
            break;
        case 'clients':
            $rows = q_all("SELECT id, name, email, phone, address, status, created_at FROM users WHERE role = 'owner' ORDER BY name");
            break;
        default:
            $rows = q_all('SELECT p.id, p.name, p.species, p.breed, p.sex, p.date_of_birth, p.weight_kg, p.microchip, p.status, o.name AS owner, o.phone AS owner_phone
                           FROM pets p JOIN users o ON o.id = p.owner_id ORDER BY p.name');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '-' . today() . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel opens UTF-8 correctly
    if ($rows) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) {
            // Neutralise spreadsheet formulas in user-entered text.
            fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r));
        }
    }
    fclose($out);
    exit;
}

function notifications_list(): array
{
    $u = require_login();
    return q_all('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 50', [$u['id']]);
}

function notifications_read(): array
{
    $u = require_login();
    if ($id = opt_id('id')) {
        q('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$id, $u['id']]);
    } else {
        q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$u['id']]);
    }
    return ['unread' => (int)q_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$u['id']])];
}

// ---------------------------------------------------------------------------
// Activity log
// ---------------------------------------------------------------------------
function activity_list(): array
{
    require_role('admin');
    $sql = 'SELECT l.*, u.name AS user_name, u.role AS user_role FROM activity_log l LEFT JOIN users u ON u.id = l.user_id WHERE 1=1';
    $params = [];
    if ($uid = opt_id('user_id')) {
        $sql .= ' AND l.user_id = ?';
        $params[] = $uid;
    }
    if ($d = v_date(opt('date'), 'date', false)) {
        $sql .= ' AND l.created_at BETWEEN ? AND ?';
        array_push($params, "$d 00:00:00", "$d 23:59:59");
    }
    if ($area = opt('area')) {
        $sql .= ' AND l.entity_type = ?';
        $params[] = $area;
    }
    if ($s = opt('q')) {
        $sql .= ' AND (l.summary LIKE ? OR u.name LIKE ?)';
        array_push($params, "%$s%", "%$s%");
    }
    return q_all($sql . ' ORDER BY l.id DESC LIMIT 300', $params);
}
