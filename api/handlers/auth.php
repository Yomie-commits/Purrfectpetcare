<?php
/** Authentication, registration, password reset and profile. */
defined('APP_LOADED') or exit;

function clinic_public(): array
{
    return [
        'name' => setting('clinic_name', 'Purrfect Pet Care'),
        'phone' => setting('clinic_phone', ''),
        'email' => setting('clinic_email', ''),
        'address' => setting('clinic_address', ''),
        'hours' => setting('clinic_hours', ''),
        'open_time' => setting('open_time', '08:00'),
        'close_time' => setting('close_time', '19:00'),
        'closed_days' => setting('closed_days', '0'),
        'currency' => 'KSh',
    ];
}

function auth_me(): array
{
    $u = current_user();
    if ($u) {
        maybe_run_reminders();
    }
    return [
        'user' => $u,
        'csrf' => $_SESSION['csrf'],
        'clinic' => clinic_public(),
        'unread' => $u ? (int)q_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$u['id']]) : 0,
        'perms' => $u ? user_perms($u) : [],
    ];
}

function auth_public_info(): array
{
    return [
        'clinic' => clinic_public(),
        'services' => q_all('SELECT id, name, description, price, duration_minutes FROM services WHERE active = 1 ORDER BY price, name'),
    ];
}

function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    q('UPDATE users SET last_login = ? WHERE id = ?', [now(), $u['id']]);
}

function auth_login(): array
{
    $email = v_email(req('email'));
    $password = req('password');
    $since = date('Y-m-d H:i:s', time() - 15 * 60);
    $fails = (int)q_val('SELECT COUNT(*) FROM login_attempts WHERE (email = ? OR ip = ?) AND attempted_at > ?', [$email, client_ip(), $since]);
    if ($fails >= 8) {
        fail('Too many failed attempts. Please wait 15 minutes and try again.', 429);
    }
    $u = q_one('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$u || !password_verify($password, $u['password_hash'])) {
        insert('login_attempts', ['email' => $email, 'ip' => client_ip(), 'attempted_at' => now()]);
        fail('Incorrect email or password', 401);
    }
    if ($u['status'] === 'pending') {
        fail('Your account is waiting for approval by a clinic administrator. Please try again later.', 403);
    }
    if ($u['status'] !== 'active') {
        fail('This account has been deactivated. Please contact the clinic.', 403);
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    q('DELETE FROM login_attempts WHERE email = ?', [$email]);
    login_user($u);
    return ['user' => current_user(), 'csrf' => $_SESSION['csrf']];
}

function auth_register(): array
{
    $name = req('name', 'Full name', 120);
    $email = v_email(req('email'));
    $password = v_password(req('password'));
    $phone = v_phone(opt('phone'));
    $role = v_enum(opt('role', 'owner'), ['owner', 'vet', 'reception', 'assistant', 'admin'], 'account type');
    if (q_val('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) {
        fail('An account with this email already exists. Try logging in.');
    }
    // Pet parents can use their account straight away. Staff sign-ups
    // stay "pending" until an existing administrator approves them, so nobody
    // can give themselves staff access to client records.
    $status = $role === 'owner' ? 'active' : 'pending';
    $id = insert('users', [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'status' => $status,
        'specialization' => $role === 'vet' ? opt('specialization') : null,
        'created_at' => now(),
    ]);
    if ($status === 'pending') {
        $label = ROLE_LABELS[$role];
        notify_role('admin', 'New ' . $label . ' account to approve', "$name ($email) signed up as a $label. Approve or delete the account under Users.", 'users?status=pending');
        return [
            'pending' => true,
            'csrf' => $_SESSION['csrf'],
            'message' => "Thanks, $name! Your $label account has been created and is waiting for approval by a clinic administrator. You can log in once it is approved.",
        ];
    }
    notify($id, 'Welcome to ' . setting('clinic_name', 'Purrfect Pet Care') . '!', 'Start by adding your pets, then book your first appointment.', 'pets');
    login_user(['id' => $id]);
    return ['user' => current_user(), 'csrf' => $_SESSION['csrf']];
}

function auth_logout(): array
{
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return ['csrf' => $_SESSION['csrf']];
}

function auth_forgot(): array
{
    $email = v_email(req('email'));
    $u = q_one("SELECT id, name, email FROM users WHERE email = ? AND status = 'active'", [$email]);
    $msg = 'If that email is registered, a reset link has been sent. It expires in 1 hour.';
    if ($u) {
        $token = bin2hex(random_bytes(32));
        insert('password_resets', [
            'user_id' => $u['id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => now(),
        ]);
        $link = app_url() . '/reset-password.html?token=' . $token;
        $sent = send_mail($u['email'], 'Reset your password',
            "Hi {$u['name']},\n\nUse this link to set a new password (valid for 1 hour):\n$link\n\nIf you didn't ask for this, ignore this email.");
        if (!$sent && env('APP_DEBUG') === 'true') {
            return ['message' => $msg, 'debug_link' => $link];
        }
    }
    return ['message' => $msg];
}

function auth_reset(): array
{
    $token = req('token');
    $password = v_password(req('password'));
    $r = q_one('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL', [hash('sha256', $token)]);
    if (!$r || strtotime($r['expires_at']) < time()) {
        fail('This reset link is invalid or has expired. Please request a new one.');
    }
    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $r['user_id']]);
    q('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $r['user_id']]);
    return ['message' => 'Password updated. You can now log in.'];
}

function auth_update_profile(): array
{
    $u = require_login();
    $data = [
        'name' => req('name', 'Full name', 120),
        'phone' => v_phone(opt('phone')),
        'address' => opt('address'),
        'emergency_name' => opt('emergency_name'),
        'emergency_phone' => v_phone(opt('emergency_phone')),
    ];
    if ($u['role'] === 'vet') {
        $data['specialization'] = opt('specialization');
    }
    $email = v_email(req('email'));
    if ($email !== $u['email']) {
        if (q_val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) {
            fail('That email is already used by another account');
        }
        $data['email'] = $email;
    }
    update('users', $data, $u['id']);
    return q_one('SELECT id, name, email, phone, address, emergency_name, emergency_phone, role, status, specialization, created_at FROM users WHERE id = ?', [$u['id']]);
}

function auth_change_password(): array
{
    $u = require_login();
    $hash = q_val('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
    if (!password_verify(req('current_password', 'Current password'), $hash)) {
        fail('Your current password is incorrect');
    }
    $new = v_password(req('new_password', 'New password'));
    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
    session_regenerate_id(true);
    return ['message' => 'Password changed'];
}

function auth_delete_account(): array
{
    $u = require_login();
    $hash = q_val('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
    if (!password_verify(req('password'), $hash)) {
        fail('Password is incorrect');
    }
    if ($u['role'] === 'admin' && (int)q_val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'") <= 1) {
        fail('You are the only administrator. Create another admin before deleting this account.');
    }
    purge_user_files($u['id']);
    q('DELETE FROM users WHERE id = ?', [$u['id']]);
    $_SESSION = [];
    session_regenerate_id(true);
    return ['message' => 'Your account has been deleted'];
}

/** In-app (and email) reminders for vaccinations due within 14 days. */
function send_vaccination_reminders(?int $ownerId = null): int
{
    $limit = date('Y-m-d', strtotime('+14 days'));
    $sql = 'SELECT v.id, v.vaccine, v.next_due, p.name AS pet, u.id AS uid, u.email, u.name AS uname
            FROM vaccinations v JOIN pets p ON p.id = v.pet_id JOIN users u ON u.id = p.owner_id
            WHERE v.reminder_sent = 0 AND v.next_due IS NOT NULL AND v.next_due <= ? AND p.status = ?';
    $params = [$limit, 'active'];
    if ($ownerId) {
        $sql .= ' AND u.id = ?';
        $params[] = $ownerId;
    }
    $rows = q_all($sql, $params);
    foreach ($rows as $r) {
        $when = $r['next_due'] < today() ? 'was due on' : 'is due on';
        $msg = "{$r['pet']}'s {$r['vaccine']} vaccination $when " . date('j M Y', strtotime($r['next_due'])) . '. Book an appointment to stay protected.';
        notify((int)$r['uid'], 'Vaccination reminder', $msg, 'vaccinations');
        send_mail($r['email'], 'Vaccination reminder for ' . $r['pet'], "Hi {$r['uname']},\n\n$msg\n\n" . app_url());
        q('UPDATE vaccinations SET reminder_sent = 1 WHERE id = ?', [$r['id']]);
    }
    return count($rows);
}
