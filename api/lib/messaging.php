<?php
/**
 * Outgoing SMS and email, and the daily reminders.
 *
 * Providers are switched on by adding keys to .env (nothing else to change):
 *   SMS   - Africa's Talking: AT_USERNAME, AT_API_KEY, optional AT_SENDER_ID
 *           (AT_USERNAME=sandbox uses their free test environment)
 *   Email - Brevo (HTTPS API, works where PHP mail() is blocked): BREVO_API_KEY + MAIL_FROM
 *           otherwise PHP mail() when MAIL_ENABLED=true
 * Every message is written to message_log, so the clinic can see what was sent.
 */
defined('APP_LOADED') or exit;

function sms_provider(): string
{
    if (!env('AT_USERNAME') || !env('AT_API_KEY')) {
        return 'off';
    }
    return env('AT_USERNAME') === 'sandbox' ? 'sandbox' : 'live';
}

function email_provider(): string
{
    if (env('BREVO_API_KEY')) {
        return 'brevo';
    }
    return env('MAIL_ENABLED') === 'true' ? 'mail' : 'off';
}

/** Kenyan numbers to +2547XXXXXXXX; others kept if already international. */
function sms_number(?string $phone): ?string
{
    $p = preg_replace('/\D/', '', (string)$phone);
    if ($p === '') {
        return null;
    }
    if (strlen($p) === 10 && $p[0] === '0') {
        $p = '254' . substr($p, 1);
    } elseif (strlen($p) === 9 && in_array($p[0], ['7', '1'], true)) {
        $p = '254' . $p;
    }
    return strlen($p) >= 11 ? '+' . $p : null;
}

function real_email(?string $email): ?string
{
    return $email && substr($email, -13) !== '@walkin.local' && substr($email, -11) !== '@demo.local' ? $email : null;
}

/** Low-level HTTP POST used by both providers. Returns [httpCode, decodedBody|raw]. */
function msg_http_post(string $url, array $headers, string $body): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return [0, $err ?: 'No response'];
    }
    $json = json_decode($raw, true);
    return [$code, $json ?? $raw];
}

/** Send one SMS. Returns ['status' => sent|failed|skipped, 'ref' => ?, 'error' => ?]. */
function sms_send(string $to, string $text): array
{
    $mode = sms_provider();
    $num = sms_number($to);
    if ($mode === 'off') {
        return ['status' => 'skipped', 'error' => 'SMS is not set up'];
    }
    if (!$num) {
        return ['status' => 'failed', 'error' => 'No valid phone number'];
    }
    $host = $mode === 'sandbox' ? 'https://api.sandbox.africastalking.com' : 'https://api.africastalking.com';
    $fields = ['username' => env('AT_USERNAME'), 'to' => $num, 'message' => $text];
    if (env('AT_SENDER_ID') && $mode === 'live') {
        $fields['from'] = env('AT_SENDER_ID');
    }
    [$code, $res] = msg_http_post($host . '/version1/messaging', [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'apiKey: ' . env('AT_API_KEY'),
    ], http_build_query($fields));
    $rec = is_array($res) ? ($res['SMSMessageData']['Recipients'][0] ?? null) : null;
    if ($code >= 200 && $code < 300 && $rec && in_array((int)($rec['statusCode'] ?? 0), [100, 101, 102], true)) {
        return ['status' => 'sent', 'ref' => $rec['messageId'] ?? null];
    }
    $why = $rec['status'] ?? (is_array($res) ? ($res['SMSMessageData']['Message'] ?? json_encode($res)) : (string)$res);
    return ['status' => 'failed', 'error' => mb_substr("HTTP $code: $why", 0, 250)];
}

/** Send one email. Same return shape as sms_send(). */
function email_send(string $to, string $subject, string $text): array
{
    $mode = email_provider();
    if ($mode === 'off') {
        return ['status' => 'skipped', 'error' => 'Email is not set up'];
    }
    if ($mode === 'mail') {
        return send_mail($to, $subject, $text) ? ['status' => 'sent'] : ['status' => 'failed', 'error' => 'PHP mail() failed'];
    }
    $from = env('MAIL_FROM') ?: (setting('clinic_email') ?: 'no-reply@example.com');
    [$code, $res] = msg_http_post('https://api.brevo.com/v3/smtp/email', [
        'Accept: application/json',
        'Content-Type: application/json',
        'api-key: ' . env('BREVO_API_KEY'),
    ], json_encode([
        'sender' => ['name' => setting('clinic_name', 'Purrfect Pet Care'), 'email' => $from],
        'to' => [['email' => $to]],
        'replyTo' => ['email' => setting('clinic_email') ?: $from],
        'subject' => $subject,
        'textContent' => $text,
    ]));
    if ($code >= 200 && $code < 300) {
        return ['status' => 'sent', 'ref' => is_array($res) ? ($res['messageId'] ?? null) : null];
    }
    return ['status' => 'failed', 'error' => mb_substr("HTTP $code: " . (is_array($res) ? ($res['message'] ?? json_encode($res)) : $res), 0, 250)];
}

/**
 * Tell a person something on every channel the clinic has switched on:
 * in-app notification always, SMS and email when set up.
 * $refKey stops the same reminder going out twice.
 */
function message_user(int $userId, string $kind, string $refKey, string $subject, string $text, string $link = ''): array
{
    if ($refKey !== '' && q_val('SELECT COUNT(*) FROM message_log WHERE ref_key = ?', [$refKey])) {
        return ['duplicate' => true];
    }
    $u = q_one('SELECT id, name, email, phone FROM users WHERE id = ?', [$userId]);
    if (!$u) {
        return [];
    }
    notify($userId, $subject, $text, $link);
    $out = [];
    $channels = [];
    // Demo accounts never get real SMS or email: their phone numbers may belong to real people.
    $isDemo = substr((string)$u['email'], -11) === '@demo.local';
    if ($isDemo) {
        $u['phone'] = null;
    }
    if (setting('msg_sms', '1') === '1' && sms_provider() !== 'off' && sms_number($u['phone'])) {
        $channels['sms'] = fn() => sms_send($u['phone'], $text . ' - ' . setting('clinic_name', 'Purrfect Pet Care'));
    }
    if (setting('msg_email', '1') === '1' && email_provider() !== 'off' && real_email($u['email'])) {
        $channels['email'] = fn() => email_send($u['email'], $subject, "Hi {$u['name']},\n\n$text\n\n" . setting('clinic_name', 'Purrfect Pet Care') . ' · ' . setting('clinic_phone', '') . "\n" . app_url());
    }
    if (!$channels) {
        $channels['app'] = fn() => ['status' => 'sent'];
    }
    $first = true;
    foreach ($channels as $ch => $send) {
        $r = $send();
        insert('message_log', [
            'user_id' => $userId, 'channel' => $ch, 'kind' => $kind,
            'to_addr' => $ch === 'sms' ? sms_number($u['phone']) : ($ch === 'email' ? $u['email'] : null),
            'subject' => mb_substr($subject, 0, 150), 'body' => $text,
            'status' => $r['status'], 'provider_ref' => $r['ref'] ?? null, 'error' => $r['error'] ?? null,
            // Only the first log row carries the key, so the unique index blocks repeats.
            'ref_key' => $first && $refKey !== '' ? $refKey : null,
            'created_at' => now(),
        ]);
        $first = false;
        $out[$ch] = $r['status'];
    }
    return $out;
}

function fill_template(string $tpl, array $vars): string
{
    $vars += ['clinic' => setting('clinic_name', 'Purrfect Pet Care'), 'phone' => setting('clinic_phone', '')];
    return strtr($tpl, array_combine(array_map(fn($k) => '{' . $k . '}', array_keys($vars)), array_values($vars)));
}

const DEFAULT_TEMPLATES = [
    'tpl_vaccine' => '{pet} is due for {item} on {date}. Book a visit with {clinic}: {phone}',
    'tpl_appointment' => 'Reminder: {pet} has {item} tomorrow ({date}) at {time} with {clinic}. Call {phone} to change it.',
];

function template(string $key): string
{
    $t = trim((string)setting($key, ''));
    return $t !== '' ? $t : DEFAULT_TEMPLATES[$key];
}

/**
 * Daily reminders: vaccines/deworming/flea & tick due within 7 days (or overdue up to 30 days),
 * and appointments tomorrow. Safe to call often: each reminder goes out once.
 */
function run_reminders(): array
{
    $done = ['treatments' => 0, 'appointments' => 0];
    $from = date('Y-m-d', strtotime('-30 days'));
    $to = date('Y-m-d', strtotime('+7 days'));
    $due = q_all("SELECT v.id, v.vaccine, v.kind, v.next_due, p.name AS pet, p.owner_id
                  FROM vaccinations v JOIN pets p ON p.id = v.pet_id
                  WHERE p.status = 'active' AND v.next_due BETWEEN ? AND ?
                    AND NOT EXISTS (SELECT 1 FROM vaccinations v2 WHERE v2.pet_id = v.pet_id AND v2.vaccine = v.vaccine AND v2.date_given > v.date_given)", [$from, $to]);
    $kindWord = ['vaccine' => 'the %s vaccine', 'deworming' => 'deworming (%s)', 'flea_tick' => 'flea & tick treatment (%s)'];
    foreach ($due as $v) {
        $item = sprintf($kindWord[$v['kind'] ?? 'vaccine'] ?? '%s', $v['vaccine']);
        $text = fill_template(template('tpl_vaccine'), ['pet' => $v['pet'], 'item' => $item, 'date' => date('j M Y', strtotime($v['next_due']))]);
        $r = message_user((int)$v['owner_id'], 'treatment_due', 'due:' . $v['id'] . ':' . $v['next_due'], 'Reminder: ' . $v['pet'] . ' is due for ' . $item, $text, 'vaccinations?tab=due');
        if (empty($r['duplicate'])) {
            $done['treatments']++;
        }
    }
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $appts = q_all("SELECT a.id, a.appt_time, a.owner_id, p.name AS pet, s.name AS service
                    FROM appointments a JOIN pets p ON p.id = a.pet_id LEFT JOIN services s ON s.id = a.service_id
                    WHERE a.appt_date = ? AND a.status IN ('pending','confirmed')", [$tomorrow]);
    foreach ($appts as $a) {
        $text = fill_template(template('tpl_appointment'), ['pet' => $a['pet'], 'item' => $a['service'] ?: 'an appointment', 'date' => date('D j M', strtotime($tomorrow)), 'time' => substr($a['appt_time'], 0, 5)]);
        $r = message_user((int)$a['owner_id'], 'appointment', 'appt:' . $a['id'] . ':' . $tomorrow . ':' . substr($a['appt_time'], 0, 5), 'Appointment tomorrow', $text, 'appointments');
        if (empty($r['duplicate'])) {
            $done['appointments']++;
        }
    }
    set_setting('reminders_last_run', now());
    return $done;
}

/**
 * Free hosting has no scheduled jobs, so reminders run on the first visit to the
 * dashboard each day after 7am (and from cron/reminders.php where cron exists).
 */
function maybe_run_reminders(): void
{
    if (setting('msg_auto', '1') !== '1' || (int)date('G') < 7) {
        return;
    }
    $last = (string)setting('reminders_last_run', '');
    if (substr($last, 0, 10) === today()) {
        return;
    }
    set_setting('reminders_last_run', now()); // claim today's run first so parallel requests skip it
    try {
        run_reminders();
    } catch (Throwable $e) {
        error_log('Reminders failed: ' . $e->getMessage());
    }
}
