<?php
/** Pets and appointments. */
defined('APP_LOADED') or exit;

const SPECIES = ['Dog', 'Cat', 'Bird', 'Rabbit', 'Fish', 'Reptile', 'Rodent', 'Other'];
const APPT_STATUSES = ['pending', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'];

/** Load a pet and make sure the current user may see it. */
function pet_for_user(int $petId, array $u): array
{
    $p = q_one('SELECT * FROM pets WHERE id = ?', [$petId]);
    if (!$p || (!is_staff($u) && (int)$p['owner_id'] !== $u['id'])) {
        fail('Pet not found', 404);
    }
    return $p;
}

function pets_list(): array
{
    $u = require_login();
    $sql = "SELECT p.*, u.name AS owner_name, u.phone AS owner_phone, CASE WHEN u.email LIKE '%@demo.local' THEN 1 ELSE 0 END AS is_demo,
                   (SELECT MAX(a.id) FROM attachments a WHERE a.entity_type = 'pet' AND a.entity_id = p.id AND a.kind = 'photo') AS photo_id
            FROM pets p JOIN users u ON u.id = p.owner_id WHERE 1=1";
    $params = [];
    if (!is_staff($u)) {
        $sql .= ' AND p.owner_id = ?';
        $params[] = $u['id'];
    } elseif ($oid = opt_id('owner_id')) {
        $sql .= ' AND p.owner_id = ?';
        $params[] = $oid;
    }
    if ($s = opt('q')) {
        $sql .= ' AND (p.name LIKE ? OR u.name LIKE ? OR p.breed LIKE ? OR p.microchip LIKE ?)';
        $like = '%' . $s . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $sql .= ' ORDER BY p.status, p.name';
    return q_all($sql, $params);
}

function pet_get(): array
{
    $u = require_login();
    $p = pet_for_user(req_id(), $u);
    $owner = q_one('SELECT id, name, email, phone, emergency_name, emergency_phone FROM users WHERE id = ?', [$p['owner_id']]);
    $p['owner'] = $owner;
    $p['photo_id'] = q_val("SELECT MAX(id) FROM attachments WHERE entity_type = 'pet' AND entity_id = ? AND kind = 'photo'", [$p['id']]);
    $p['documents'] = q_all("SELECT id, original_name, mime, size_bytes, created_at FROM attachments WHERE entity_type = 'pet' AND entity_id = ? AND kind = 'document' ORDER BY id DESC", [$p['id']]);
    $p['records'] = (!is_staff($u) || can($u, 'medical.view')) ? array_map('record_decorate', q_all("SELECT r.*, v.name AS vet_name, (SELECT COUNT(*) FROM attachments a WHERE a.entity_type = 'record' AND a.entity_id = r.id) AS file_count,
                           (SELECT COUNT(*) FROM record_versions rv WHERE rv.record_id = r.id) AS version_count
                           FROM health_records r LEFT JOIN users v ON v.id = r.vet_id WHERE r.pet_id = ? ORDER BY r.record_date DESC, r.id DESC", [$p['id']])) : null;
    $p['balance'] = owner_balance((int)$p['owner_id']);
    $p['vaccinations'] = array_map('vaccination_with_status', q_all('SELECT * FROM vaccinations WHERE pet_id = ? ORDER BY date_given DESC', [$p['id']]));
    $p['appointments'] = q_all('SELECT a.*, s.name AS service_name, v.name AS vet_name FROM appointments a LEFT JOIN services s ON s.id = a.service_id LEFT JOIN users v ON v.id = a.vet_id WHERE a.pet_id = ? ORDER BY a.appt_date DESC, a.appt_time DESC', [$p['id']]);
    return $p;
}

function pet_save(): array
{
    $u = require_login();
    $id = opt_id('id');
    $data = [
        'name' => req('name', 'Pet name', 100),
        'species' => v_enum(opt('species'), SPECIES, 'species'),
        'breed' => opt('breed'),
        'sex' => opt('sex') ? v_enum(opt('sex'), ['Male', 'Female', 'Unknown'], 'sex') : null,
        'date_of_birth' => v_date(opt('date_of_birth'), 'date of birth', false),
        'weight_kg' => v_decimal(opt('weight_kg'), 'weight'),
        'color' => opt('color'),
        'microchip' => opt('microchip'),
        'notes' => opt('notes'),
        'allergies' => opt('allergies'),
        'conditions' => opt('conditions'),
        'medications' => opt('medications'),
        'diet' => opt('diet'),
        'behaviour' => opt('behaviour'),
    ];
    if ($data['date_of_birth'] && $data['date_of_birth'] > today()) {
        fail('Date of birth cannot be in the future');
    }
    if ($id) {
        $pet = pet_for_user($id, $u);
        $status = opt('status');
        if ($status) {
            $data['status'] = v_enum($status, ['active', 'deceased', 'archived'], 'status');
        }
        if (is_staff($u) && ($oid = opt_id('owner_id'))) {
            $data['owner_id'] = owner_id_or_fail($oid);
        }
        update('pets', $data, $id);
        if ($data['weight_kg'] && abs((float)$data['weight_kg'] - (float)$pet['weight_kg']) > 0.0001) {
            log_pet_weight($id, (float)$data['weight_kg'], today(), $u['id'], 'Updated on profile');
        }
    } else {
        $data['owner_id'] = is_staff($u) ? owner_id_or_fail(req_id('owner_id')) : $u['id'];
        $data['status'] = 'active';
        $data['created_at'] = now();
        $id = insert('pets', $data);
        if ($data['weight_kg']) {
            log_pet_weight($id, (float)$data['weight_kg'], today(), $u['id'], 'Weight when registered');
        }
    }
    return q_one('SELECT * FROM pets WHERE id = ?', [$id]);
}

function owner_id_or_fail(int $id): int
{
    if (!q_val("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'owner'", [$id])) {
        fail('Selected owner not found');
    }
    return $id;
}

function pet_delete(): array
{
    $u = require_login();
    $pet = pet_for_user(req_id(), $u);
    if (is_staff($u) && $u['role'] !== 'admin') {
        fail('Only the owner or an administrator can delete a pet', 403);
    }
    purge_pet_files([(int)$pet['id']]);
    q('DELETE FROM pets WHERE id = ?', [$pet['id']]);
    return ['deleted' => (int)$pet['id']];
}

// ---------------------------------------------------------------------------
// Appointments
// ---------------------------------------------------------------------------
function appointment_select(): string
{
    return "SELECT a.*, p.name AS pet_name, p.species, p.allergies AS pet_allergies, p.conditions AS pet_conditions, p.behaviour AS pet_behaviour, s.category AS service_category, o.name AS owner_name, o.phone AS owner_phone, o.email AS owner_email, CASE WHEN o.email LIKE '%@demo.local' THEN 1 ELSE 0 END AS is_demo,
                   v.name AS vet_name, s.name AS service_name,
                   COALESCE((SELECT SUM(amount) FROM payments WHERE appointment_id = a.id AND status = 'completed'), 0) AS amount_paid
            FROM appointments a
            JOIN pets p ON p.id = a.pet_id
            JOIN users o ON o.id = a.owner_id
            LEFT JOIN users v ON v.id = a.vet_id
            LEFT JOIN services s ON s.id = a.service_id";
}

function appointment_decorate(array $a): array
{
    $a['amount_paid'] = (float)$a['amount_paid'];
    $a['price'] = (float)$a['price'];
    $a['balance'] = in_array($a['status'], ['cancelled', 'no_show'], true) ? 0.0 : max(0, round($a['price'] - $a['amount_paid'], 2));
    $a['appt_time'] = substr($a['appt_time'], 0, 5);
    return $a;
}

function appointment_for_user(int $id, array $u): array
{
    $a = q_one(appointment_select() . ' WHERE a.id = ?', [$id]);
    if (!$a || (!is_staff($u) && (int)$a['owner_id'] !== $u['id'])) {
        fail('Appointment not found', 404);
    }
    return appointment_decorate($a);
}

function appointments_list(): array
{
    $u = require_login();
    $sql = appointment_select() . ' WHERE 1=1';
    $params = [];
    if (!is_staff($u)) {
        $sql .= ' AND a.owner_id = ?';
        $params[] = $u['id'];
    } elseif (opt('scope') === 'mine' && $u['role'] === 'vet') {
        $sql .= ' AND (a.vet_id = ? OR a.vet_id IS NULL)';
        $params[] = $u['id'];
    }
    if ($st = opt('status')) {
        if ($st === 'upcoming') {
            $sql .= " AND a.status IN ('pending','confirmed','checked_in','in_progress') AND a.appt_date >= ?";
            $params[] = today();
        } else {
            $sql .= ' AND a.status = ?';
            $params[] = v_enum($st, APPT_STATUSES, 'status');
        }
    }
    if ($d = v_date(opt('date'), 'date', false)) {
        $sql .= ' AND a.appt_date = ?';
        $params[] = $d;
    }
    if (($from = v_date(opt('from'), 'start date', false)) && ($to = v_date(opt('to'), 'end date', false))) {
        $sql .= ' AND a.appt_date BETWEEN ? AND ?';
        array_push($params, $from, $to);
    }
    if ($vid = opt_id('vet_id')) {
        $sql .= ' AND a.vet_id = ?';
        $params[] = $vid;
    }
    if (opt('unpaid') === '1') {
        $sql .= " AND a.status = 'completed' AND a.price > COALESCE((SELECT SUM(amount) FROM payments WHERE appointment_id = a.id AND status = 'completed'), 0) + 0.001";
    }
    if ($oid = opt_id('owner_id')) {
        $sql .= ' AND a.owner_id = ?';
        $params[] = $oid;
    }
    if ($pid = opt_id('pet_id')) {
        $sql .= ' AND a.pet_id = ?';
        $params[] = $pid;
    }
    $sql .= ' ORDER BY a.appt_date DESC, a.appt_time DESC LIMIT 500';
    return array_map('appointment_decorate', q_all($sql, $params));
}

/** Available 30-minute slots for a day (optionally for one vet). */
function appointment_slots(): array
{
    require_login();
    $date = v_date(opt('date'));
    $vetId = opt_id('vet_id');
    return compute_slots($date, $vetId);
}

function compute_slots(string $date, ?int $vetId, ?int $ignoreId = null): array
{
    $closed = array_filter(explode(',', setting('closed_days', '0')), 'strlen');
    $weekday = (int)date('w', strtotime($date));
    if (in_array((string)$weekday, $closed, true)) {
        return [];
    }
    $open = strtotime($date . ' ' . setting('open_time', '08:00'));
    $close = strtotime($date . ' ' . setting('close_time', '19:00'));
    $step = 30 * 60;

    // Which vets could see patients on this day, and during which hours.
    $vets = $vetId ? [$vetId] : array_map('intval', array_column(q_all("SELECT id FROM users WHERE role = 'vet' AND status = 'active'"), 'id'));
    $hours = []; // vet => [[startTs, endTs], ...]
    foreach ($vets as $v) {
        $hasSchedule = (int)q_val('SELECT COUNT(*) FROM vet_schedules WHERE vet_id = ?', [$v]);
        if (!$hasSchedule) {
            $hours[$v] = [[$open, $close]]; // no personal schedule: clinic hours
        } else {
            $hours[$v] = array_map(fn($r) => [strtotime("$date {$r['start_time']}"), strtotime("$date {$r['end_time']}")],
                q_all('SELECT start_time, end_time FROM vet_schedules WHERE vet_id = ? AND weekday = ?', [$v, $weekday]));
        }
        if (q_val('SELECT COUNT(*) FROM vet_time_off WHERE vet_id = ? AND ? BETWEEN date_from AND date_to', [$v, $date])) {
            $hours[$v] = [];
        }
    }

    $bookedByVet = [];
    $unassigned = [];
    foreach (q_all("SELECT appt_time, vet_id FROM appointments WHERE appt_date = ? AND status NOT IN ('cancelled','no_show') AND id <> ?", [$date, $ignoreId ?? 0]) as $r) {
        $t = substr($r['appt_time'], 0, 5);
        if ($r['vet_id']) {
            $bookedByVet[(int)$r['vet_id']][$t] = true;
        } else {
            $unassigned[$t] = ($unassigned[$t] ?? 0) + 1;
        }
    }

    $slots = [];
    for ($t = $open; $t + $step <= $close; $t += $step) {
        $hm = date('H:i', $t);
        if ($date === today() && $t <= time() + 30 * 60) {
            continue;
        }
        $free = 0;
        foreach ($hours as $v => $ranges) {
            $working = false;
            foreach ($ranges as [$a, $b]) {
                if ($t >= $a && $t + $step <= $b) {
                    $working = true;
                    break;
                }
            }
            if ($working && empty($bookedByVet[$v][$hm])) {
                $free++;
            }
        }
        if (!$vets) {
            $free = 1; // clinic without vet accounts yet: one booking per slot
            if (!empty($bookedByVet) && array_filter($bookedByVet, fn($x) => isset($x[$hm]))) {
                $free = 0;
            }
        }
        $needed = $vetId ? 1 : ($unassigned[$hm] ?? 0) + 1;
        if ($vetId ? $free >= 1 : $free >= $needed) {
            $slots[] = $hm;
        }
    }
    return $slots;
}

function appointment_book(): array
{
    $u = require_login();
    $pet = pet_for_user(req_id('pet_id'), $u);
    if ($pet['status'] !== 'active') {
        fail('Appointments can only be booked for active pets');
    }
    $service = q_one('SELECT * FROM services WHERE id = ? AND active = 1', [req_id('service_id')]);
    if (!$service) {
        fail('Please choose a valid service');
    }
    $date = v_date(opt('date'));
    $time = v_time(opt('time'));
    if ($date < today()) {
        fail('Please choose a date in the future');
    }
    if ($date > date('Y-m-d', strtotime('+6 months'))) {
        fail('Appointments can be booked up to 6 months ahead');
    }
    $vetId = opt_id('vet_id');
    if ($vetId && !q_val("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'vet' AND status = 'active'", [$vetId])) {
        fail('Selected vet is not available');
    }
    if (!in_array(substr($time, 0, 5), compute_slots($date, $vetId), true)) {
        fail('That time slot is not available. Please pick another time.', 409);
    }
    $id = insert('appointments', [
        'owner_id' => (int)$pet['owner_id'],
        'pet_id' => (int)$pet['id'],
        'vet_id' => $vetId,
        'service_id' => (int)$service['id'],
        'appt_date' => $date,
        'appt_time' => $time,
        'reason' => opt('reason'),
        'status' => is_staff($u) ? 'confirmed' : 'pending',
        'price' => (float)$service['price'],
        'created_at' => now(),
    ]);
    if ((float)$service['price'] > 0) {
        insert('appointment_items', ['appointment_id' => $id, 'item_type' => 'service', 'description' => $service['name'], 'quantity' => 1, 'unit_price' => (float)$service['price'], 'created_by' => $u['id'], 'created_at' => now()]);
    }
    $when = date('D j M Y', strtotime($date)) . ' at ' . substr($time, 0, 5);
    if (is_staff($u)) {
        notify((int)$pet['owner_id'], 'Appointment booked', "{$service['name']} for {$pet['name']} on $when.", 'appointments');
    } else {
        $msg = "{$u['name']} booked {$service['name']} for {$pet['name']} on $when.";
        if ($vetId) {
            notify($vetId, 'New appointment request', $msg, 'appointments');
        } else {
            notify_role('vet', 'New appointment request', $msg, 'appointments');
        }
    }
    return appointment_for_user($id, $u);
}

function appointment_status(): array
{
    $u = require_login();
    $a = appointment_for_user(req_id(), $u);
    $status = v_enum(opt('status'), APPT_STATUSES, 'status');
    $data = ['status' => $status];

    if (!is_staff($u)) {
        if ($status !== 'cancelled') {
            fail('You can only cancel your appointments', 403);
        }
        if (!in_array($a['status'], ['pending', 'confirmed'], true)) {
            fail('This appointment can no longer be cancelled');
        }
    } else {
        $allowed = [
            'pending' => ['confirmed', 'checked_in', 'cancelled'],
            'confirmed' => ['checked_in', 'in_progress', 'completed', 'cancelled', 'no_show', 'pending'],
            'checked_in' => ['in_progress', 'completed', 'cancelled', 'confirmed'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => ['pending'],
            'no_show' => ['confirmed'],
        ];
        if (!in_array($status, $allowed[$a['status']] ?? [], true)) {
            fail('Cannot change an appointment from ' . str_replace('_', ' ', $a['status']) . ' to ' . str_replace('_', ' ', $status));
        }
        $isCheckIn = $status === 'checked_in' || ($a['status'] === 'checked_in' && $status === 'confirmed');
        if (!can($u, $isCheckIn ? 'checkin' : 'appointments.manage')) {
            fail('You do not have permission to do that', 403);
        }
        if ($status === 'checked_in') {
            $data['checked_in_at'] = now();
        } elseif ($a['status'] === 'checked_in' && $status === 'confirmed') {
            $data['checked_in_at'] = null;
        }
        if ($u['role'] === 'vet' && !$a['vet_id'] && in_array($status, ['confirmed', 'in_progress', 'completed'], true)) {
            $data['vet_id'] = $u['id']; // the vet takes the unassigned appointment
        }
        if (($notes = opt('vet_notes')) !== null) {
            $data['vet_notes'] = $notes;
        }
    }
    update('appointments', $data, (int)$a['id']);

    $labels = ['confirmed' => 'confirmed', 'checked_in' => 'checked in', 'in_progress' => 'started', 'completed' => 'completed', 'cancelled' => 'cancelled', 'no_show' => 'marked as missed', 'pending' => 'reopened'];
    $when = date('j M', strtotime($a['appt_date'])) . ' ' . $a['appt_time'];
    if (is_staff($u) && !in_array($status, ['checked_in'], true) && !($a['status'] === 'checked_in' && $status === 'confirmed')) {
        $extra = ($status === 'completed' && $a['balance'] > 0) ? ' Amount due: ' . money($a['balance']) . '.' : '';
        notify((int)$a['owner_id'], 'Appointment ' . $labels[$status], "{$a['pet_name']}'s {$a['service_name']} on $when was {$labels[$status]}.$extra", 'appointments');
    } elseif ($a['vet_id']) {
        notify((int)$a['vet_id'], 'Appointment cancelled', "{$a['owner_name']} cancelled {$a['pet_name']}'s appointment on $when.", 'appointments');
    }
    return appointment_for_user((int)$a['id'], $u);
}

function appointment_reschedule(): array
{
    $u = require_login();
    if (is_staff($u) && !can($u, 'appointments.manage')) {
        fail('You do not have permission to do that', 403);
    }
    $a = appointment_for_user(req_id(), $u);
    if (!in_array($a['status'], ['pending', 'confirmed'], true)) {
        fail('Only pending or confirmed appointments can be rescheduled');
    }
    $date = v_date(opt('date'));
    $time = v_time(opt('time'));
    if ($date < today()) {
        fail('Please choose a date in the future');
    }
    $vetId = $a['vet_id'] ? (int)$a['vet_id'] : null;
    if (!in_array(substr($time, 0, 5), compute_slots($date, $vetId, (int)$a['id']), true)) {
        fail('That time slot is not available. Please pick another time.', 409);
    }
    update('appointments', [
        'appt_date' => $date,
        'appt_time' => $time,
        'status' => is_staff($u) ? $a['status'] : 'pending',
    ], (int)$a['id']);
    $when = date('D j M Y', strtotime($date)) . ' at ' . substr($time, 0, 5);
    if (is_staff($u)) {
        notify((int)$a['owner_id'], 'Appointment rescheduled', "{$a['pet_name']}'s appointment moved to $when.", 'appointments');
    } elseif ($vetId) {
        notify($vetId, 'Appointment rescheduled', "{$a['owner_name']} moved {$a['pet_name']}'s appointment to $when.", 'appointments');
    }
    return appointment_for_user((int)$a['id'], $u);
}

function appointment_assign(): array
{
    $u = require_perm('appointments.manage');
    $a = appointment_for_user(req_id(), $u);
    $vetId = opt_id('vet_id');
    if ($vetId) {
        if (!q_val("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'vet' AND status = 'active'", [$vetId])) {
            fail('Vet not found');
        }
        $clash = q_val("SELECT COUNT(*) FROM appointments WHERE vet_id = ? AND appt_date = ? AND appt_time = ? AND id <> ? AND status NOT IN ('cancelled','no_show')",
            [$vetId, $a['appt_date'], $a['appt_time'] . ':00', $a['id']]);
        if ($clash) {
            fail('That vet already has an appointment at this time', 409);
        }
        notify($vetId, 'Appointment assigned to you', "{$a['pet_name']} ({$a['service_name']}) on {$a['appt_date']} {$a['appt_time']}.", 'appointments');
    }
    update('appointments', ['vet_id' => $vetId], (int)$a['id']);
    return appointment_for_user((int)$a['id'], $u);
}

/** Total unpaid on completed or ongoing visits for one pet parent. */
function owner_balance(int $ownerId): float
{
    $bal = 0.0;
    foreach (q_all(appointment_select() . " WHERE a.owner_id = ? AND a.status IN ('completed','checked_in','in_progress')", [$ownerId]) as $a) {
        $bal += appointment_decorate($a)['balance'];
    }
    return round($bal, 2);
}

/**
 * Register a walk-in: find or create the pet parent and pet, then open a
 * checked-in visit for right now (no booking slot needed).
 */
function walkin_create(): array
{
    $u = require_perm('appointments.manage');
    $service = q_one('SELECT * FROM services WHERE id = ? AND active = 1', [req_id('service_id')]);
    if (!$service) {
        fail('Please choose a valid service');
    }
    $vetId = opt_id('vet_id');
    if ($vetId && !q_val("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'vet' AND status = 'active'", [$vetId])) {
        fail('Selected vet is not available');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($ownerId = opt_id('owner_id')) {
            owner_id_or_fail($ownerId);
        } else {
            $name = req('owner_name', 'Pet parent name', 120);
            $phone = v_phone(req('owner_phone', 'Phone number'));
            $email = opt('owner_email') ? v_email(opt('owner_email')) : null;
            $existing = $email ? q_one('SELECT id, role FROM users WHERE email = ?', [$email]) : null;
            if (!$existing && $phone) {
                $existing = q_one("SELECT id, role FROM users WHERE phone = ? AND role = 'owner'", [$phone]);
            }
            if ($existing) {
                if ($existing['role'] !== 'owner') {
                    fail('That email belongs to a staff account');
                }
                $ownerId = (int)$existing['id'];
            } else {
                // Walk-in clients without email get a placeholder they can change later.
                $ownerId = insert('users', [
                    'name' => $name,
                    'email' => $email ?: 'walkin-' . preg_replace('/\D/', '', $phone) . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '@walkin.local',
                    'phone' => $phone,
                    'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                    'role' => 'owner',
                    'status' => 'active',
                    'created_at' => now(),
                ]);
            }
        }
        if ($petId = opt_id('pet_id')) {
            $pet = q_one('SELECT * FROM pets WHERE id = ? AND owner_id = ?', [$petId, $ownerId]);
            if (!$pet) {
                fail('That pet does not belong to the selected pet parent');
            }
        } else {
            $petId = insert('pets', [
                'owner_id' => $ownerId,
                'name' => req('pet_name', 'Pet name', 100),
                'species' => v_enum(opt('species'), SPECIES, 'species'),
                'breed' => opt('breed'),
                'status' => 'active',
                'created_at' => now(),
            ]);
            $pet = q_one('SELECT * FROM pets WHERE id = ?', [$petId]);
        }
        $time = date('H:i:00', (int)(floor(time() / 300) * 300));
        $id = insert('appointments', [
            'owner_id' => $ownerId,
            'pet_id' => (int)$pet['id'],
            'vet_id' => $vetId,
            'service_id' => (int)$service['id'],
            'appt_date' => today(),
            'appt_time' => $time,
            'reason' => opt('reason'),
            'status' => 'checked_in',
            'source' => 'walk_in',
            'checked_in_at' => now(),
            'price' => (float)$service['price'],
            'created_at' => now(),
        ]);
        if ((float)$service['price'] > 0) {
            insert('appointment_items', ['appointment_id' => $id, 'item_type' => 'service', 'description' => $service['name'], 'quantity' => 1, 'unit_price' => (float)$service['price'], 'created_by' => $u['id'], 'created_at' => now()]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    if ($vetId) {
        notify($vetId, 'Walk-in waiting', "{$pet['name']} ({$service['name']}) has arrived.", 'appointments');
    }
    return appointment_for_user($id, $u);
}
