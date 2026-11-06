<?php
/**
 * Phase 2: weight history, digital health cards (QR), enquiries and messaging settings.
 */
defined('APP_LOADED') or exit;

// ---------------------------------------------------------------------------
// Weight history
// ---------------------------------------------------------------------------

/** Add a weight point, and keep the pet's current weight in step with the latest one. */
function log_pet_weight(int $petId, float $kg, string $date, ?int $userId, ?string $note = null, ?int $recordId = null): void
{
    if ($kg <= 0) {
        return;
    }
    if ($recordId) {
        q('DELETE FROM pet_weights WHERE record_id = ?', [$recordId]); // an edited record replaces its point
    }
    insert('pet_weights', ['pet_id' => $petId, 'weight_kg' => $kg, 'measured_on' => $date, 'note' => $note, 'record_id' => $recordId, 'created_by' => $userId, 'created_at' => now()]);
    $latest = q_one('SELECT weight_kg FROM pet_weights WHERE pet_id = ? ORDER BY measured_on DESC, id DESC LIMIT 1', [$petId]);
    if ($latest) {
        q('UPDATE pets SET weight_kg = ? WHERE id = ?', [$latest['weight_kg'], $petId]);
    }
}

function pet_weights(): array
{
    $u = require_login();
    return pet_weights_for(req_id('pet_id'), $u);
}

function pet_weights_for(int $petId, array $u): array
{
    $pet = pet_for_user($petId, $u);
    $rows = array_map(function ($w) {
        $w['weight_kg'] = (float)$w['weight_kg'];
        return $w;
    }, q_all('SELECT w.*, u.name AS by_name FROM pet_weights w LEFT JOIN users u ON u.id = w.created_by WHERE w.pet_id = ? ORDER BY w.measured_on, w.id', [$pet['id']]));
    return ['pet' => ['id' => (int)$pet['id'], 'name' => $pet['name'], 'species' => $pet['species']], 'weights' => $rows];
}

function weight_add(): array
{
    $u = require_login();
    $pet = pet_for_user(req_id('pet_id'), $u);
    if (is_staff($u) && !can($u, 'treatment.write') && !can($u, 'appointments.manage')) {
        fail('You do not have permission to do that', 403);
    }
    $kg = v_decimal(opt('weight_kg'), 'weight');
    if (!$kg || $kg <= 0 || $kg > 1000) {
        fail('Enter the weight in kg, e.g. 12.5');
    }
    $date = v_date(opt('measured_on', today()), 'date');
    if ($date > today()) {
        fail('The date cannot be in the future');
    }
    log_pet_weight((int)$pet['id'], $kg, $date, $u['id'], opt('note') ? mb_substr(opt('note'), 0, 200) : null);
    return pet_weights_for((int)$pet['id'], $u);
}

function weight_delete(): array
{
    $u = require_login();
    $w = q_one('SELECT * FROM pet_weights WHERE id = ?', [req_id()]);
    if (!$w) {
        fail('Not found', 404);
    }
    $pet = pet_for_user((int)$w['pet_id'], $u);
    if ($w['record_id']) {
        fail('This weight comes from a health record. Change it there.');
    }
    if (!is_staff($u) && (int)$w['created_by'] !== $u['id']) {
        fail('You can only remove weights you added', 403);
    }
    q('DELETE FROM pet_weights WHERE id = ?', [$w['id']]);
    $latest = q_one('SELECT weight_kg FROM pet_weights WHERE pet_id = ? ORDER BY measured_on DESC, id DESC LIMIT 1', [$pet['id']]);
    q('UPDATE pets SET weight_kg = ? WHERE id = ?', [$latest['weight_kg'] ?? null, $pet['id']]);
    return pet_weights_for((int)$pet['id'], $u);
}

// ---------------------------------------------------------------------------
// Digital health card with QR code
// ---------------------------------------------------------------------------

function pet_card_save(): array
{
    $u = require_login();
    $pet = pet_for_user(req_id('pet_id'), $u);
    if (is_staff($u) && !can($u, 'appointments.manage') && !can($u, 'treatment.write')) {
        fail('You do not have permission to do that', 403);
    }
    $on = opt('enabled', '1') === '1';
    $data = ['card_show_phone' => opt('show_phone') === '1' ? 1 : 0];
    if (!$on) {
        $data['card_token'] = null;
    } elseif (!$pet['card_token'] || opt('new_link') === '1') {
        $data['card_token'] = bin2hex(random_bytes(12));
    }
    update('pets', $data, (int)$pet['id']);
    $p = q_one('SELECT id, name, card_token, card_show_phone FROM pets WHERE id = ?', [$pet['id']]);
    $p['url'] = $p['card_token'] ? app_url() . '/card.php?t=' . $p['card_token'] : null;
    return $p;
}

// ---------------------------------------------------------------------------
// Enquiries / support requests
// ---------------------------------------------------------------------------

function enquiry_notify_staff(array $e): void
{
    $msg = "{$e['name']}: {$e['subject']}";
    foreach (['reception', 'admin'] as $role) {
        notify_role($role, 'New enquiry', $msg, 'enquiries?id=' . $e['id']);
    }
}

function enquiry_create(): array
{
    $u = current_user();
    if ($u && is_staff($u)) {
        fail('Staff reply to enquiries from the Enquiries page');
    }
    $subject = req('subject', 'Subject', 150);
    $message = req('message', 'Message', 3000);
    if ($u) {
        $data = ['user_id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'phone' => $u['phone']];
        if ($pid = opt_id('pet_id')) {
            $data['pet_id'] = (int)pet_for_user($pid, $u)['id'];
        }
    } else {
        // Public contact form: simple flood control per session.
        $_SESSION['enq_count'] = ($_SESSION['enq_count'] ?? 0) + 1;
        if ($_SESSION['enq_count'] > 5) {
            fail('Too many messages. Please call the clinic instead.', 429);
        }
        if (trim((string)opt('website')) !== '') {
            return ['ok' => true]; // honeypot filled in: a bot
        }
        $email = opt('email') ? v_email(opt('email')) : null;
        $phone = v_phone(opt('phone'));
        if (!$email && !$phone) {
            fail('Please give a phone number or email so we can reply');
        }
        $data = ['user_id' => null, 'name' => req('name', 'Your name', 120), 'email' => $email, 'phone' => $phone];
    }
    $data += ['subject' => $subject, 'message' => $message, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()];
    $data['id'] = insert('enquiries', $data);
    enquiry_notify_staff($data);
    return ['id' => $data['id'], 'message' => 'Thank you! Your message has been sent. We will get back to you soon.'];
}

function enquiry_for_user(int $id, array $u): array
{
    $e = q_one('SELECT e.*, p.name AS pet_name FROM enquiries e LEFT JOIN pets p ON p.id = e.pet_id WHERE e.id = ?', [$id]);
    if (!$e || (!is_staff($u) && (int)$e['user_id'] !== $u['id'])) {
        fail('Enquiry not found', 404);
    }
    return $e;
}

function enquiries_list(): array
{
    $u = require_login();
    $sql = 'SELECT e.*, p.name AS pet_name, (SELECT COUNT(*) FROM enquiry_replies r WHERE r.enquiry_id = e.id) AS replies,
                   (SELECT r.created_at FROM enquiry_replies r WHERE r.enquiry_id = e.id ORDER BY r.id DESC LIMIT 1) AS last_reply_at
            FROM enquiries e LEFT JOIN pets p ON p.id = e.pet_id WHERE 1=1';
    $params = [];
    if (!is_staff($u)) {
        $sql .= ' AND e.user_id = ?';
        $params[] = $u['id'];
    } elseif ($st = opt('status')) {
        $sql .= ' AND e.status = ?';
        $params[] = v_enum($st, ['open', 'answered', 'closed'], 'status');
    }
    return q_all($sql . ' ORDER BY e.updated_at DESC, e.id DESC LIMIT 300', $params);
}

function enquiry_get(): array
{
    $u = require_login();
    return enquiry_full(req_id(), $u);
}

function enquiry_full(int $id, array $u): array
{
    $e = enquiry_for_user($id, $u);
    $e['thread'] = q_all('SELECT r.*, u.name AS author, u.role AS author_role FROM enquiry_replies r LEFT JOIN users u ON u.id = r.user_id WHERE r.enquiry_id = ? ORDER BY r.id', [$e['id']]);
    return $e;
}

function enquiry_reply(): array
{
    $u = require_login();
    $e = enquiry_for_user(req_id(), $u);
    if (is_staff($u) && !can($u, 'appointments.manage') && !can($u, 'medical.view')) {
        fail('You do not have permission to do that', 403);
    }
    $msg = req('message', 'Reply', 3000);
    insert('enquiry_replies', ['enquiry_id' => $e['id'], 'user_id' => $u['id'], 'message' => $msg, 'created_at' => now()]);
    $status = is_staff($u) ? 'answered' : 'open';
    update('enquiries', ['status' => $status, 'updated_at' => now()], (int)$e['id']);
    if (is_staff($u)) {
        $text = 'Reply to "' . $e['subject'] . '": ' . mb_substr($msg, 0, 300);
        if ($e['user_id']) {
            message_user((int)$e['user_id'], 'enquiry', '', 'Reply from ' . setting('clinic_name', 'the clinic'), $text, 'enquiries?id=' . $e['id']);
        } else {
            // Public enquiry: reply by SMS/email directly.
            $res = [];
            if (real_email($e['email']) && email_provider() !== 'off') {
                $res['email'] = [$e['email'], email_send($e['email'], 'Re: ' . $e['subject'], "Hi {$e['name']},\n\n$msg\n\n" . setting('clinic_name', '') . ' · ' . setting('clinic_phone', ''))];
            }
            if ($e['phone'] && sms_provider() !== 'off') {
                $res['sms'] = [$e['phone'], sms_send($e['phone'], mb_substr($text, 0, 300) . ' - ' . setting('clinic_name', ''))];
            }
            foreach ($res as $ch => [$to, $r]) {
                insert('message_log', ['user_id' => null, 'channel' => $ch, 'kind' => 'enquiry', 'to_addr' => $to, 'subject' => mb_substr('Re: ' . $e['subject'], 0, 150), 'body' => $msg, 'status' => $r['status'], 'provider_ref' => $r['ref'] ?? null, 'error' => $r['error'] ?? null, 'created_at' => now()]);
            }
        }
    } else {
        enquiry_notify_staff($e);
    }
    return enquiry_full((int)$e['id'], $u);
}

function enquiry_status(): array
{
    $u = require_staff();
    $e = enquiry_for_user(req_id(), $u);
    update('enquiries', ['status' => v_enum(opt('status'), ['open', 'answered', 'closed'], 'status'), 'updated_at' => now()], (int)$e['id']);
    return enquiry_full((int)$e['id'], $u);
}

// ---------------------------------------------------------------------------
// Messaging settings (admin)
// ---------------------------------------------------------------------------

function messaging_get(): array
{
    require_role('admin');
    return [
        'sms' => sms_provider(),
        'email' => email_provider(),
        'msg_sms' => setting('msg_sms', '1'),
        'msg_email' => setting('msg_email', '1'),
        'msg_auto' => setting('msg_auto', '1'),
        'tpl_vaccine' => template('tpl_vaccine'),
        'tpl_appointment' => template('tpl_appointment'),
        'last_run' => setting('reminders_last_run'),
        'log' => q_all('SELECT m.*, u.name AS user_name FROM message_log m LEFT JOIN users u ON u.id = m.user_id ORDER BY m.id DESC LIMIT 60'),
    ];
}

function messaging_save(): array
{
    require_role('admin');
    foreach (['msg_sms', 'msg_email', 'msg_auto'] as $k) {
        set_setting($k, opt($k) === '1' ? '1' : '0');
    }
    foreach (['tpl_vaccine', 'tpl_appointment'] as $k) {
        $v = trim((string)opt($k));
        if (mb_strlen($v) > 320) {
            fail('Keep messages under 320 characters (2 SMS)');
        }
        set_setting($k, $v === DEFAULT_TEMPLATES[$k] ? '' : $v);
    }
    return messaging_get();
}

function reminders_run(): array
{
    require_role('admin');
    $r = run_reminders();
    return $r + ['message' => "Reminders sent: {$r['treatments']} vaccine/treatment and {$r['appointments']} appointment reminder(s). Anything already sent was skipped."];
}

function message_test(): array
{
    $u = require_role('admin');
    $to = req('to', 'Phone number or email');
    $text = 'Test message from ' . setting('clinic_name', 'Purrfect Pet Care') . '. SMS and email are working.';
    $r = strpos($to, '@') !== false ? email_send(v_email($to), 'Test message', $text) : sms_send($to, $text);
    insert('message_log', ['user_id' => $u['id'], 'channel' => strpos($to, '@') !== false ? 'email' : 'sms', 'kind' => 'test', 'to_addr' => $to, 'subject' => 'Test message', 'body' => $text, 'status' => $r['status'], 'provider_ref' => $r['ref'] ?? null, 'error' => $r['error'] ?? null, 'created_at' => now()]);
    return $r + ['message' => $r['status'] === 'sent' ? 'Test message sent.' : ($r['status'] === 'skipped' ? 'Not sent: ' . $r['error'] . '. Add the keys to .env first.' : 'Sending failed: ' . ($r['error'] ?? 'unknown error'))];
}
